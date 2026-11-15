<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Api;

use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaUnitami;
use App\OpenCode\Tests\Support\ImitaciyaVypolneniyaHoda;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Сквозные тесты HTTP-контракта сессий OpenCode.
 *
 * JWT проверяется по-настоящему: токен выписывается энкодером Lexik, а
 * `JWTDecodedListener` подгружает пользователя из `jwt_user`, поэтому в
 * каждом тесте создаётся реальная строка аккаунта. Temporal не поднимается:
 * порт выполнения хода подменён на `ImitaciyaVypolneniyaHoda`, который
 * записывает вызовы и позволяет «доставить» результат так, как это позже
 * сделал бы опрос workflow.
 *
 * Unitman тоже подменён (`ImitaciyaUpravleniyaUnitami`): тесты юнитов задают
 * состояние и доступ явно, поэтому проверяют контракт OpenCode, а не сборку
 * контейнеров. Состояние юнита влияет на ходы через единый гейт
 * `PropuskHodaOpenCode`, поэтому проверка «собирается → ход запрещён»
 * остаётся сквозной.
 *
 * Все запросы идут в одном ядре (`disableReboot`), иначе подмена сервисов и
 * открытая транзакция терялись бы между запросами сценария.
 */
final class OpenCodeControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private ImitaciyaVypolneniyaHoda $runner;

    private ImitaciyaUpravleniyaUnitami $unitami;

    private Connection $db;

    private string $accountId;

    private string $credentialId;

    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();

        $this->runner = new ImitaciyaVypolneniyaHoda();
        $container->set(UmeetVypolnyatHodOpenCode::class, $this->runner);

        $this->unitami = new ImitaciyaUpravleniyaUnitami();
        $container->set(UmeetUpravlyatUnitami::class, $this->unitami);

        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $this->accountId = 'acc-' . uniqid('', true);
        $this->email = uniqid('opencode_api_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => $this->accountId,
            'email' => $this->email,
            'password' => 'pass',
            'roles' => 'ROLE_USER',
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        $this->credentialId = 'cred-' . uniqid('', true);

        $now = time();

        $this->db->insert('opencode_credential', [
            'id' => $this->credentialId,
            'account_id' => $this->accountId,
            'name' => 'local-' . uniqid('', true),
            'url' => 'https://opencode.local',
            'model' => 'gpt-4o',
            'api_key_cipher' => 'cipher-blob',
            'api_key_hint' => '****abcd',
            'cipher_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $encoder = $container->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        $this->client->setServerParameter(
            'HTTP_Authorization',
            'Bearer ' . $encoder->encode(['username' => $this->email, 'password' => 'pass'])
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $telo
     *
     * @return array<string, mixed>
     */
    private function zapros(string $method, string $path, ?array $telo = null): array
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $telo === null ? null : json_encode($telo, JSON_THROW_ON_ERROR),
        );

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertIsArray($content, 'Ответ должен быть JSON-конвертом');

        return $content;
    }

    /**
     * @param array<string, mixed> $otvet
     *
     * @return mixed
     */
    private function dannye(array $otvet)
    {
        $this->assertSame(
            'success',
            $otvet['status'] ?? null,
            json_encode($otvet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $this->assertArrayHasKey('data', $otvet);

        return $otvet['data'];
    }

    private function trebovatOtkaz(array $otvet): string
    {
        $this->assertSame('fail', $otvet['status'] ?? null);
        $this->assertResponseStatusCodeSame(500);

        $message = $otvet['data']['message'] ?? null;

        $this->assertIsString($message);
        $this->assertNotSame('', $message);

        return $message;
    }

    private function sozdatSessiyu(?string $title = null): string
    {
        $telo = ['credentialId' => $this->credentialId];

        if ($title !== null) {
            $telo['title'] = $title;
        }

        $otvet = $this->zapros('POST', '/api/opencode/sozdatSessiyu', $telo);
        $data = $this->dannye($otvet);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);

        $sessionId = $data['id'];
        $this->assertIsString($sessionId);
        $this->assertNotSame('', $sessionId);

        return $sessionId;
    }

    /**
     * @param array<string, mixed> $telo
     *
     * @return array{turnId: string, status: string, progressChannel: string}
     */
    private function zapustit(string $path, array $telo): array
    {
        $data = $this->dannye($this->zapros('POST', $path, $telo));

        $this->assertIsArray($data);
        $this->assertArrayHasKey('turnId', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('progressChannel', $data);

        /** @var array{turnId: string, status: string, progressChannel: string} $data */
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function oprositHod(string $turnId): array
    {
        $data = $this->dannye($this->zapros('POST', '/api/opencode/poluchitResultatHoda', ['turnId' => $turnId]));

        $this->assertIsArray($data);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function vopros(string $id = 'q1', string $text = 'Что делаем?'): array
    {
        return [
            'Id' => $id,
            'Text' => $text,
            'AllowFreeText' => false,
            'Options' => [
                ['Id' => 'o1', 'Label' => 'Вариант 1', 'Description' => null],
                ['Id' => 'o2', 'Label' => 'Вариант 2', 'Description' => 'Альтернатива'],
            ],
        ];
    }

    #[Test]
    public function sozdannaya_sessiya_popalyaet_v_spisok_tekushhego_polzovatelya(): void
    {
        $sessionId = $this->sozdatSessiyu('Моя сессия');

        $data = $this->dannye($this->zapros('GET', '/api/opencode/spisokSessiy'));

        $this->assertIsArray($data);

        $nashli = null;

        foreach ($data as $element) {
            $this->assertIsArray($element);

            if (($element['id'] ?? null) === $sessionId) {
                $nashli = $element;
            }
        }

        $this->assertIsArray($nashli, 'Созданная сессия должна быть в списке');
        $this->assertSame($this->credentialId, $nashli['credentialId']);
        $this->assertSame('Моя сессия', $nashli['title']);
        $this->assertNull($nashli['externalSessionId']);
    }

    #[Test]
    public function prompt_zapuskaet_hod_i_peredayot_kontekst_ranmeru(): void
    {
        $sessionId = $this->sozdatSessiyu();

        $zapusk = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Привет']);

        $this->assertSame('RUNNING', $zapusk['status']);
        $this->assertNotSame('', $zapusk['turnId']);
        $this->assertSame(
            'opencode.progress.' . $this->runner->posledniiWorkflowId(),
            $zapusk['progressChannel'],
        );

        $vyzov = $this->runner->posledniiVyzov();

        $this->assertIsArray($vyzov);
        $this->assertSame($this->accountId, $vyzov['accountId']);
        $this->assertSame($this->credentialId, $vyzov['credentialId']);
        $this->assertNull($vyzov['sessionId'], 'Первый ход ещё не знает внешней сессии OpenCode');
        $this->assertSame('Привет', $vyzov['prompt']);
        $this->assertSame([], $vyzov['answers']);
        $this->assertSame([], $vyzov['history'], 'История пуста: это первый ход сессии');

        $this->assertSame(
            'Привет',
            (string) $this->db->fetchOne('select title from opencode_session where id = ?', [$sessionId]),
            'Заголовок сессии подставляется из первого промпта',
        );
    }

    #[Test]
    public function vopros_ranmera_perevodit_hod_v_ozhidanie_otveta(): void
    {
        $sessionId = $this->sozdatSessiyu();
        $zapusk = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Сделай что-нибудь']);

        $this->runner->zadatRezultatDlyaPoslednegoHoda(
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_QUESTION,
                [$this->vopros()],
                null,
                null,
                null,
            )
        );

        $hod = $this->oprositHod($zapusk['turnId']);

        $this->assertSame('AWAITING_INPUT', $hod['status']);
        $this->assertNull($hod['errorKey']);
        $this->assertIsArray($hod['result']);
        $this->assertSame('QUESTION', $hod['result']['Kind']);
        $this->assertSame('q1', $hod['result']['Questions'][0]['Id']);
        $this->assertSame('o1', $hod['result']['Questions'][0]['Options'][0]['Id']);
        $this->assertSame($zapusk['progressChannel'], $hod['progressChannel']);
    }

    #[Test]
    public function otvet_na_vopros_zapuskaet_novyj_hod_s_kontekstom(): void
    {
        $sessionId = $this->sozdatSessiyu();
        $pervyj = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Привет']);

        $this->runner->zadatRezultatDlyaPoslednegoHoda(
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_QUESTION,
                [$this->vopros()],
                null,
                null,
                null,
            )
        );

        $this->oprositHod($pervyj['turnId']);

        $vtoroj = $this->zapustit('/api/opencode/otvetit', [
            'turnId' => $pervyj['turnId'],
            'answers' => [['questionId' => 'q1', 'optionId' => 'o1']],
        ]);

        $this->assertSame('RUNNING', $vtoroj['status']);
        $this->assertNotSame($pervyj['turnId'], $vtoroj['turnId']);
        $this->assertSame(2, $this->runner->kolichestvoVyzovov());

        $vyzov = $this->runner->posledniiVyzov();

        $this->assertIsArray($vyzov);
        $this->assertSame('Что делаем?: Вариант 1', $vyzov['prompt']);
        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => null]],
            $vyzov['answers'],
        );
        $this->assertSame(
            [
                ['Role' => 'user', 'Text' => 'Привет'],
                ['Role' => 'assistant', 'Text' => 'Что делаем?'],
            ],
            $vyzov['history'],
        );
    }

    #[Test]
    public function otvet_fiksiruet_vneshnyuyu_sessiyu_i_zavershaet_hod(): void
    {
        $sessionId = $this->sozdatSessiyu();
        $zapusk = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Сделай']);

        $this->runner->zadatRezultatDlyaPoslednegoHoda(
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                'ext-1',
            )
        );

        $hod = $this->oprositHod($zapusk['turnId']);

        $this->assertSame('ANSWERED', $hod['status']);
        $this->assertIsArray($hod['result']);
        $this->assertSame('ANSWER', $hod['result']['Kind']);
        $this->assertSame('Готово', $hod['result']['Answer']);
        $this->assertNotNull($hod['finishedAt']);

        $this->assertSame(
            'ext-1',
            (string) $this->db->fetchOne('select external_session_id from opencode_session where id = ?', [$sessionId]),
            'Внешняя сессия запоминается, чтобы следующий ход продолжил диалог',
        );
    }

    #[Test]
    public function istoriya_sessii_vozvrashhaet_hody_po_vozrastaniyu(): void
    {
        $sessionId = $this->sozdatSessiyu();
        $pervyj = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Первый']);

        $this->runner->zadatRezultatDlyaPoslednegoHoda(
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_QUESTION,
                [$this->vopros()],
                null,
                null,
                null,
            )
        );
        $this->oprositHod($pervyj['turnId']);

        $vtoroj = $this->zapustit('/api/opencode/otvetit', [
            'turnId' => $pervyj['turnId'],
            'answers' => [['questionId' => 'q1', 'optionId' => 'o2']],
        ]);

        $data = $this->dannye($this->zapros('POST', '/api/opencode/istoriyaSessii', ['sessionId' => $sessionId]));

        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        $this->assertSame(1, $data[0]['seq']);
        $this->assertSame('Первый', $data[0]['requestText']);
        $this->assertSame('AWAITING_INPUT', $data[0]['status']);

        $this->assertSame(2, $data[1]['seq']);
        $this->assertSame('Что делаем?: Вариант 2', $data[1]['requestText']);
        $this->assertSame('RUNNING', $data[1]['status']);
        $this->assertSame($vtoroj['turnId'], $data[1]['id']);
        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => 'o2', 'Text' => null]],
            $data[1]['answers'],
        );
    }

    #[Test]
    public function progress_hoda_null_poka_ranmer_ne_prislal_shagi(): void
    {
        $sessionId = $this->sozdatSessiyu();
        $zapusk = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Сделай']);

        $pustoj = $this->zapros('POST', '/api/opencode/poluchitProgressHoda', ['turnId' => $zapusk['turnId']]);

        $this->assertSame('success', $pustoj['status']);
        $this->assertArrayHasKey('data', $pustoj);
        $this->assertNull($pustoj['data']);

        $this->runner->zadatProgressDlyaPoslednegoHoda(
            new ProgressHodaOpenCode(
                [['Command' => 'ls', 'Response' => 'file.txt', 'Success' => true, 'Unixtime' => 123]],
                true,
                true,
                1700000000,
            )
        );

        $data = $this->dannye($this->zapros('POST', '/api/opencode/poluchitProgressHoda', ['turnId' => $zapusk['turnId']]));

        $this->assertIsArray($data);
        $this->assertTrue($data['finished']);
        $this->assertTrue($data['success']);
        $this->assertSame(1700000000, $data['updatedAt']);
        $this->assertSame('ls', $data['steps'][0]['Command']);
    }

    #[Test]
    public function chuzhaya_sessiya_nedostupna(): void
    {
        $now = time();

        $this->db->insert('opencode_session', [
            'id' => 'foreign-session',
            'account_id' => 'another-account',
            'credential_id' => $this->credentialId,
            'title' => 'Чужая',
            'external_session_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/prompt', ['sessionId' => 'foreign-session', 'text' => 'Привет'])
        );

        $this->assertSame(0, $this->runner->kolichestvoVyzovov(), 'Чужой ход не должен уходить в раннер');
    }

    #[Test]
    public function pustoj_prompt_otklonyaetsya(): void
    {
        $sessionId = $this->sozdatSessiyu();

        $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => '   '])
        );

        $this->assertSame(0, $this->runner->kolichestvoVyzovov());
    }

    #[Test]
    public function otvetit_na_hod_ne_ozhidayushhij_otveta_nelzya(): void
    {
        $sessionId = $this->sozdatSessiyu();
        $zapusk = $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Сделай']);

        $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/otvetit', [
                'turnId' => $zapusk['turnId'],
                'answers' => [['questionId' => 'q1', 'optionId' => 'o1']],
            ])
        );

        $this->assertSame(1, $this->runner->kolichestvoVyzovov(), 'Второй ход не должен стартовать');
    }

    #[Test]
    public function bez_validnogo_tokena_otvet_401(): void
    {
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer slomannyj.token.znachenie');

        $this->client->request('GET', '/api/opencode/spisokSessiy');

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function arhivaciya_ostavlyaet_sessiyu_i_istoriyu_v_spiske(): void
    {
        $sessionId = $this->sozdatSessiyu('Архивируемая сессия');
        $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Первый']);

        $this->assertSame(
            1,
            (int) $this->db->fetchOne('select count(*) from opencode_turn where session_id = ?', [$sessionId]),
            'Перед архивацией у сессии должен быть хотя бы один ход',
        );

        $otvet = $this->zapros('POST', '/api/opencode/arhivirovatSessiyu', ['sessionId' => $sessionId]);

        $this->assertSame('success', $otvet['status'] ?? null, json_encode($otvet, JSON_UNESCAPED_UNICODE));
        $this->assertNull($otvet['data'] ?? null, 'Успешная архивация отвечает пустым data');

        $this->assertSame(
            1,
            (int) $this->db->fetchOne('select count(*) from opencode_session where id = ?', [$sessionId]),
            'Сессия не удаляется, а остаётся в архиве',
        );
        $this->assertSame(
            1,
            (int) $this->db->fetchOne('select count(*) from opencode_turn where session_id = ?', [$sessionId]),
            'История ходов сохраняется: архивация не стирает диалог',
        );
        $this->assertSame(
            'manual',
            (string) $this->db->fetchOne('select archive_reason from opencode_session where id = ?', [$sessionId]),
        );

        $spisok = $this->dannye($this->zapros('GET', '/api/opencode/spisokSessiy'));
        $nashli = $this->najtiVSspiske($spisok, $sessionId);

        $this->assertIsArray($nashli, 'Архивная сессия остаётся в списке по умолчанию');
        $this->assertTrue($nashli['archived']);
        $this->assertSame('manual', $nashli['archiveReason']);
    }

    #[Test]
    public function arhivnaya_sessiya_ne_prinimaet_prompt(): void
    {
        $sessionId = $this->sozdatSessiyu('Архивная');

        $this->dannye($this->zapros('POST', '/api/opencode/arhivirovatSessiyu', ['sessionId' => $sessionId]));

        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Ещё раз'])
        );

        $this->assertNotSame('', $message);
        $this->assertSame(0, $this->runner->kolichestvoVyzovov(), 'Ход из архива не уходит в раннер');
    }

    #[Test]
    public function udalit_sessiyu_rabotaet_kak_arhivaciya(): void
    {
        $sessionId = $this->sozdatSessiyu('Устаревший алиас');

        $otvet = $this->zapros('POST', '/api/opencode/udalitSessiyu', ['sessionId' => $sessionId]);

        $this->assertSame('success', $otvet['status'] ?? null, json_encode($otvet, JSON_UNESCAPED_UNICODE));
        $this->assertNull($otvet['data'] ?? null);
        $this->assertSame(
            'manual',
            (string) $this->db->fetchOne('select archive_reason from opencode_session where id = ?', [$sessionId]),
            'Устаревший маршрут удаления архивирует сессию',
        );
    }

    #[Test]
    public function chuzhuyu_sessiyu_arhivirovat_nelzya(): void
    {
        $now = time();

        $this->db->insert('opencode_session', [
            'id' => 'foreign-deletable-session',
            'account_id' => 'another-account',
            'credential_id' => $this->credentialId,
            'title' => 'Чужая',
            'external_session_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/arhivirovatSessiyu', ['sessionId' => 'foreign-deletable-session'])
        );

        $this->assertNotSame('', $message);
        $this->assertNull(
            $this->db->fetchOne(
                'select archived_at from opencode_session where id = ?',
                ['foreign-deletable-session'],
            ),
            'Чужая сессия не должна архивироваться',
        );
    }

    #[Test]
    public function arhivaciya_neizvestnoj_sessii_otklonyaetsya(): void
    {
        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/arhivirovatSessiyu', ['sessionId' => 'no-such-session'])
        );

        $this->assertNotSame('', $message);
    }

    #[Test]
    public function arhivaciya_sessii_s_pustym_id_otklonyaetsya(): void
    {
        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/arhivirovatSessiyu', ['sessionId' => '   '])
        );

        $this->assertNotSame('', $message);
    }

    #[Test]
    public function privezka_k_unitu_peredayot_ego_direktoriyu_v_hod(): void
    {
        $this->unitami->zadatSostoyanieUnita('unit-1', $this->sostoyanieUnita(SostoyanieUnita::GROUP_READY));

        $sessionId = $this->sozdatSessiyu('Свободная сессия');

        $otvet = $this->zapros('POST', '/api/opencode/privezatKUnitu', [
            'sessionId' => $sessionId,
            'unitIds' => ['unit-1'],
        ]);

        $this->assertSame('success', $otvet['status'] ?? null, json_encode($otvet, JSON_UNESCAPED_UNICODE));
        $this->assertNull($otvet['data'] ?? null);

        $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Работаем в юните']);

        $vyzov = $this->runner->posledniiVyzov();

        $this->assertIsArray($vyzov);
        $this->assertSame('unit-1', $vyzov['unitId'], 'Ход идёт в директории привязанного юнита');
        $this->assertSame('project-1', $vyzov['unitProjectId'], 'Снимок проекта передаётся в активити');
        $this->assertSame(
            [['unitId' => 'unit-1', 'unitProjectId' => 'project-1']],
            $vyzov['units'],
            'Список юнитов уходит в раннер как multi-root workspace',
        );

        $this->assertSame(
            'unit-1',
            (string) $this->db->fetchOne('select unit_id from opencode_session where id = ?', [$sessionId]),
            'Легаси-колонка хранит первый юнит',
        );
        $this->assertSame(
            'project-1',
            (string) $this->db->fetchOne('select unit_project_id from opencode_session where id = ?', [$sessionId]),
        );
    }

    #[Test]
    public function privezka_dobavlyaet_neskolko_unitov_v_odnom_zaprose(): void
    {
        // Юниты из разных проектов: один проект может дать только один юнит сессии.
        $this->zadatGotovyeUnity(['unit-1' => 'project-1', 'unit-2' => 'project-2', 'unit-3' => 'project-3']);

        $sessionId = $this->sozdatSessiyu();

        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', [
            'sessionId' => $sessionId,
            'unitIds' => ['unit-1', 'unit-2', 'unit-3'],
        ]));

        $this->assertSame(
            ['unit-1', 'unit-2', 'unit-3'],
            $this->db->fetchFirstColumn(
                'select unit_id from opencode_session_unit where session_id = ? order by position_index',
                [$sessionId],
            ),
            'Порядок юнитов сохраняется: первый задаёт cwd',
        );

        $this->zapustit('/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Мульти-рут']);

        $this->assertSame(
            ['unit-1', 'unit-2', 'unit-3'],
            $this->runner->unitIdsPoslednegoHoda(),
            'Все юниты передаются в раннер, первый — рабочий каталог',
        );
    }

    #[Test]
    public function povtornaya_privezka_togo_zhe_unita_idempotentna(): void
    {
        $this->zadatGotovyeUnity(['unit-1' => 'project-1', 'unit-2' => 'project-2']);

        $sessionId = $this->sozdatSessiyu();

        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-1']]));

        // Повтор той же привязки не ошибка: клиент может ретраить запрос.
        $this->assertSame(
            'success',
            $this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-1']])['status'] ?? null,
        );

        // Привязка второго юнита дополняет список, а не заменяет его.
        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-2']]));

        $this->assertSame(
            ['unit-1', 'unit-2'],
            $this->db->fetchFirstColumn(
                'select unit_id from opencode_session_unit where session_id = ? order by position_index',
                [$sessionId],
            ),
            'Привязка аддитивна, первый юнит остаётся первым',
        );
    }

    #[Test]
    public function vtoroj_unit_togo_zhe_proekta_otklonyaetsya(): void
    {
        $this->zadatGotovyeUnity(['unit-1' => 'project-1', 'unit-2' => 'project-1']);

        $sessionId = $this->sozdatSessiyu();

        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-1']]));

        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-2']])
        );

        $this->assertSame(
            'К сессии можно привязать только один юнит в рамках одного проекта',
            $message,
            'Второй юнит того же проекта отклоняется с понятной причиной',
        );
        $this->assertSame(
            ['unit-1'],
            $this->db->fetchFirstColumn(
                'select unit_id from opencode_session_unit where session_id = ? order by position_index',
                [$sessionId],
            ),
            'Отклонённая привязка не меняет состав сессии',
        );
    }

    #[Test]
    public function sozdanie_sessii_s_dvumya_unitami_odnogo_proekta_otklonyaetsya(): void
    {
        $this->zadatGotovyeUnity(['unit-1' => 'project-1', 'unit-2' => 'project-1']);

        $doSozdaniya = (int) $this->db->fetchOne('select count(*) from opencode_session');

        $message = $this->trebovatOtkaz($this->zapros('POST', '/api/opencode/sozdatSessiyu', [
            'credentialId' => $this->credentialId,
            'unitIds' => ['unit-1', 'unit-2'],
        ]));

        $this->assertSame(
            'К сессии можно привязать только один юнит в рамках одного проекта',
            $message,
        );
        $this->assertSame(
            $doSozdaniya,
            (int) $this->db->fetchOne('select count(*) from opencode_session'),
            'Сессия с конфликтом проектов не создаётся',
        );
    }

    #[Test]
    public function privezka_k_nedostupnomu_unitu_otklonyaetsya(): void
    {
        $this->unitami->zadatSostoyanieUnita('unit-1', $this->sostoyanieUnita(SostoyanieUnita::GROUP_READY), false);

        $sessionId = $this->sozdatSessiyu();

        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-1']])
        );

        $this->assertNotSame('', $message);
        $this->assertNull(
            $this->db->fetchOne('select unit_id from opencode_session where id = ?', [$sessionId]),
            'Недоступный юнит не привязывается',
        );
        $this->assertSame(
            [],
            $this->db->fetchFirstColumn(
                'select unit_id from opencode_session_unit where session_id = ?',
                [$sessionId],
            ),
        );
    }

    #[Test]
    public function sozdanie_sessii_s_unitami_fiksiruet_snimki_proektov(): void
    {
        $this->zadatGotovyeUnity(['unit-42' => 'project-42', 'unit-43' => 'project-43']);

        $data = $this->dannye($this->zapros('POST', '/api/opencode/sozdatSessiyu', [
            'credentialId' => $this->credentialId,
            'unitIds' => ['unit-42', 'unit-43'],
        ]));

        $this->assertIsArray($data);

        $sessionId = $data['id'];
        $this->assertIsString($sessionId);

        $this->assertSame(
            'project-42',
            (string) $this->db->fetchOne('select unit_project_id from opencode_session where id = ?', [$sessionId]),
            'Проект первого юнита снимается в момент создания сессии',
        );
        $this->assertSame(
            ['project-42', 'project-43'],
            $this->db->fetchFirstColumn(
                'select unit_project_id from opencode_session_unit where session_id = ? order by position_index',
                [$sessionId],
            ),
            'У каждого юнита свой снимок проекта',
        );
        $this->assertSame(
            ['unit-42', 'unit-43'],
            $this->db->fetchFirstColumn(
                'select unit_id from opencode_session_unit where session_id = ? order by position_index',
                [$sessionId],
            ),
        );
    }

    #[Test]
    public function prevyshenie_limita_unitov_otklonyaetsya(): void
    {
        $unitIds = [];
        $unity = [];

        // Каждый юнит — из своего проекта, иначе сработает правило «один юнит на проект».
        for ($i = 1; $i <= 11; ++$i) {
            $unitId = 'unit-' . $i;
            $unitIds[] = $unitId;
            $unity[$unitId] = 'project-' . $i;
        }

        $this->zadatGotovyeUnity($unity);

        $message = $this->trebovatOtkaz($this->zapros('POST', '/api/opencode/sozdatSessiyu', [
            'credentialId' => $this->credentialId,
            'unitIds' => $unitIds,
        ]));

        $this->assertNotSame('', $message);
    }

    #[Test]
    public function sozdanie_sessii_poruchaet_sozdat_unit_ot_imeni_polzovatelya(): void
    {
        $this->dannye($this->zapros('POST', '/api/opencode/sozdatSessiyu', [
            'credentialId' => $this->credentialId,
            'unit' => [
                'projectId' => 'project-7',
                'unitName' => 'open-code-unit',
                'branch' => 'feature/x',
            ],
        ]));

        $parametri = $this->unitami->poslednieParametriSozdaniya();

        $this->assertNotNull($parametri, 'Юнит создаётся через публичное API Unitman');
        $this->assertSame('project-7', $parametri->projectId);
        $this->assertSame('open-code-unit', $parametri->unitName);
        $this->assertSame('feature/x', $parametri->branch);
        $this->assertSame($this->accountId, $this->unitami->posledniiAccountIdSozdaniya());
    }

    #[Test]
    public function nelzya_odnovremenno_privyazat_i_sozdat_unit(): void
    {
        $message = $this->trebovatOtkaz($this->zapros('POST', '/api/opencode/sozdatSessiyu', [
            'credentialId' => $this->credentialId,
            'unitIds' => ['unit-1'],
            'unit' => [
                'projectId' => 'project-7',
                'unitName' => 'open-code-unit',
                'branch' => 'feature/x',
            ],
        ]));

        $this->assertNotSame('', $message);
        $this->assertNull($this->unitami->poslednieParametriSozdaniya(), 'Юнит не создаётся при конфликте источников');
    }

    #[Test]
    public function poka_unit_ne_gotov_hod_ne_zapuskaetsya(): void
    {
        $this->unitami->zadatSostoyanieUnita(
            'unit-1',
            $this->sostoyanieUnita(SostoyanieUnita::GROUP_BLOCKED, 'IDET_SBORKA'),
        );

        $sessionId = $this->sozdatSessiyu();
        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-1']]));

        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Рано'])
        );

        $this->assertNotSame('', $message);
        $this->assertSame(0, $this->runner->kolichestvoVyzovov(), 'Ход в несобранный юнит не уходит в раннер');
    }

    #[Test]
    public function odin_negotovyi_unit_blokiruet_hod_vo_vseh_unitah(): void
    {
        $this->unitami->zadatSostoyanieUnita(
            'unit-1',
            $this->sostoyanieUnita(SostoyanieUnita::GROUP_READY, projectId: 'project-1'),
        );
        $this->unitami->zadatSostoyanieUnita(
            'unit-2',
            $this->sostoyanieUnita(SostoyanieUnita::GROUP_BLOCKED, 'IDET_SBORKA', 'project-2'),
        );

        $sessionId = $this->sozdatSessiyu();
        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', [
            'sessionId' => $sessionId,
            'unitIds' => ['unit-1', 'unit-2'],
        ]));

        $message = $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Рано'])
        );

        $this->assertNotSame('', $message);
        $this->assertSame(0, $this->runner->kolichestvoVyzovov(), 'Ход не уходит в раннер, пока не готовы все юниты');
    }

    #[Test]
    public function udalennyj_unit_arhiviruet_sessiyu_pri_popytke_hoda(): void
    {
        $this->unitami->zadatSostoyanieUnita('unit-gone', $this->sostoyanieUnita(SostoyanieUnita::GROUP_READY));

        $sessionId = $this->sozdatSessiyu();
        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-gone']]));

        // Юнит удалили в Unitman: сессия узнаёт об этом через состояние юнита.
        $this->unitami->zadatSostoyanieUnita(
            'unit-gone',
            $this->sostoyanieUnita(SostoyanieUnita::GROUP_ARCHIVED, 'UDALEN'),
            false,
        );

        $this->trebovatOtkaz(
            $this->zapros('POST', '/api/opencode/prompt', ['sessionId' => $sessionId, 'text' => 'Ещё раз'])
        );

        $this->assertSame(
            'unit_deleted',
            (string) $this->db->fetchOne('select archive_reason from opencode_session where id = ?', [$sessionId]),
            'Сессия удалённого юнита уходит в архив автоматически',
        );
        $this->assertSame(0, $this->runner->kolichestvoVyzovov());
    }

    #[Test]
    public function sostoyanie_svobodnoj_sessii_razreshaet_hody(): void
    {
        $sessionId = $this->sozdatSessiyu();

        $data = $this->dannye($this->zapros('POST', '/api/opencode/sostoyanieSessii', ['sessionId' => $sessionId]));

        $this->assertIsArray($data);
        $this->assertSame($sessionId, $data['sessionId']);
        $this->assertSame([], $data['units'], 'Свободная сессия не имеет привязанных юнитов');
        $this->assertNull($data['unitId']);
        $this->assertNull($data['unitStateCode'], 'Свободная сессия не зависит от юнита');
        $this->assertFalse($data['archived']);
        $this->assertTrue($data['canAcceptMessages']);
    }

    #[Test]
    public function sostoyanie_privezannoj_sessii_zavisit_ot_gotovnosti_unita(): void
    {
        $this->unitami->zadatSostoyanieUnita(
            'unit-1',
            $this->sostoyanieUnita(SostoyanieUnita::GROUP_BLOCKED, 'IDET_SBORKA'),
        );

        $sessionId = $this->sozdatSessiyu();
        $this->dannye($this->zapros('POST', '/api/opencode/privezatKUnitu', ['sessionId' => $sessionId, 'unitIds' => ['unit-1']]));

        $zablokirovana = $this->dannye($this->zapros('POST', '/api/opencode/sostoyanieSessii', ['sessionId' => $sessionId]));

        $this->assertIsArray($zablokirovana);
        $this->assertSame('unit-1', $zablokirovana['unitId']);
        $this->assertSame('project-1', $zablokirovana['unitProjectId']);
        $this->assertSame('IDET_SBORKA', $zablokirovana['unitStateCode']);
        $this->assertFalse($zablokirovana['canAcceptMessages']);

        $this->assertCount(1, $zablokirovana['units']);

        $edinstvennyi = $zablokirovana['units'][0];
        $this->assertIsArray($edinstvennyi);
        $this->assertSame('unit-1', $edinstvennyi['unitId']);
        $this->assertSame('project-1', $edinstvennyi['unitProjectId']);
        $this->assertSame('IDET_SBORKA', $edinstvennyi['unitStateCode']);
        $this->assertFalse($edinstvennyi['ready']);
        $this->assertFalse($edinstvennyi['deleted']);

        // Юнит дособрался и запустился — сообщения снова принимаются.
        $this->unitami->zadatSostoyanieUnita('unit-1', $this->sostoyanieUnita(SostoyanieUnita::GROUP_READY));

        $razblokirovana = $this->dannye($this->zapros('POST', '/api/opencode/sostoyanieSessii', ['sessionId' => $sessionId]));

        $this->assertIsArray($razblokirovana);
        $this->assertSame('USPESHNO_ZAPUSHEN', $razblokirovana['unitStateCode']);
        $this->assertTrue($razblokirovana['canAcceptMessages']);
    }

    #[Test]
    public function filtr_tolko_aktivnye_skryvaet_arhivnye_sessii(): void
    {
        $aktivnaya = $this->sozdatSessiyu('Активная');
        $arhivnaya = $this->sozdatSessiyu('Архивная');

        $this->dannye($this->zapros('POST', '/api/opencode/arhivirovatSessiyu', ['sessionId' => $arhivnaya]));

        $vse = $this->dannye($this->zapros('GET', '/api/opencode/spisokSessiy'));

        $this->assertNotNull($this->najtiVSspiske($vse, $arhivnaya), 'По умолчанию архивные сессии видны');

        $tolkoAktivnye = $this->dannye($this->zapros('GET', '/api/opencode/spisokSessiy?onlyActive=true'));

        $this->assertNotNull($this->najtiVSspiske($tolkoAktivnye, $aktivnaya));
        $this->assertNull(
            $this->najtiVSspiske($tolkoAktivnye, $arhivnaya),
            'Фильтр onlyActive скрывает архивные сессии',
        );
    }

    #[Test]
    public function poisk_filtruet_spisok_po_podstroke_nazvaniya(): void
    {
        $nuzhnaya = $this->sozdatSessiyu('Починка оплаты картой');
        $drugaya = $this->sozdatSessiyu('Рефакторинг отчётов');

        $data = $this->dannye(
            $this->zapros('GET', '/api/opencode/spisokSessiy?search=' . urlencode('оплаты'))
        );

        $this->assertNotNull($this->najtiVSspiske($data, $nuzhnaya));
        $this->assertNull($this->najtiVSspiske($data, $drugaya));
    }

    /**
     * @param mixed $spisok
     *
     * @return array<string, mixed>|null
     */
    private function najtiVSspiske($spisok, string $sessionId): ?array
    {
        $this->assertIsArray($spisok);

        foreach ($spisok as $element) {
            $this->assertIsArray($element);

            if (($element['id'] ?? null) === $sessionId) {
                /** @var array<string, mixed> $element */
                return $element;
            }
        }

        return null;
    }

    /**
     * Готовит фейковые юниты Unitman: `unitId => projectId`.
     *
     * @param array<string, string> $unity
     */
    private function zadatGotovyeUnity(array $unity): void
    {
        foreach ($unity as $unitId => $projectId) {
            $this->unitami->zadatSostoyanieUnita(
                $unitId,
                $this->sostoyanieUnita(SostoyanieUnita::GROUP_READY, projectId: $projectId),
            );
        }
    }

    /**
     * Состояние фейкового юнита Unitman с заданным проектом.
     */
    private function sostoyanieUnita(
        string $group,
        string $code = 'USPESHNO_ZAPUSHEN',
        string $projectId = 'project-1',
    ): SostoyanieUnita
    {
        return new SostoyanieUnita(true, $code, $projectId, 'unit', 'main', $group);
    }
}
