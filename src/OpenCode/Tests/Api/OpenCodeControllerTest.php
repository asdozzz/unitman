<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Api;

use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
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
 * Все запросы идут в одном ядре (`disableReboot`), иначе подмена сервиса и
 * открытая транзакция терялись бы между запросами сценария.
 */
final class OpenCodeControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private ImitaciyaVypolneniyaHoda $runner;

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
        $this->assertSame('success', $otvet['status'] ?? null);
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
}
