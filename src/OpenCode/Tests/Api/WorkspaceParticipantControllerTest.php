<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Api;

use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaProektamiWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaVypolneniyaHoda;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт участников workspace.
 *
 * Сценарий повторяет `WorkspaceControllerTest`: реальная БД под транзакцией,
 * настоящий JWT и подменённые порты Runner/Unitman. Управление составом
 * проверяется от лица владельца и участников, чтобы зафиксировать коды ошибок
 * `opencode.participant.*` и `opencode.session.forbidden`.
 */
final class WorkspaceParticipantControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private ImitaciyaUpravleniyaWorkspace $manager;

    private ImitaciyaUpravleniyaProektamiWorkspace $projectami;

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

        $this->manager = new ImitaciyaUpravleniyaWorkspace();
        $container->set(UmeetUpravlyatWorkspace::class, $this->manager);

        $this->projectami = new ImitaciyaUpravleniyaProektamiWorkspace();
        $this->projectami->zadatProekty([FabrikaWorkspace::proekt('project-1', 'api', 'services/api')]);
        $this->projectami->zadatSsylkuRedaktora(
            'project-1',
            'https://47000.payments-refactor-workspac.workspaces.example.test',
        );
        $container->set(UmeetUpravlyatProektamiWorkspace::class, $this->projectami);

        $container->set(UmeetVypolnyatHodOpenCode::class, new ImitaciyaVypolneniyaHoda());

        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $this->accountId = 'acc-' . uniqid('', true);
        $this->email = uniqid('workspace_participant_', true) . '@asd.ru';

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

        $this->kak($this->email);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function spisok_vozvrashchaet_vladelca_i_priqlashennogo_i_dostupen_viewer(): void
    {
        $id = $this->sozdat();
        $writer = $this->sozdatAkkaunt();
        $viewer = $this->sozdatAkkaunt();

        $this->dobavit($id, $writer, 'writer');
        $this->dobavit($id, $viewer, 'viewer');

        $items = $this->spisok($id);

        $this->assertSame('owner', $this->rol($items, $this->accountId));
        $this->assertSame('writer', $this->rol($items, $writer['id']));
        $this->assertSame('viewer', $this->rol($items, $viewer['id']));

        // Viewer читает список наравне с остальными.
        $this->kak($this->emailDlya($viewer));

        $itemsViewer = $this->spisok($id);

        $this->assertCount(3, $itemsViewer);
        $this->assertSame('viewer', $this->rol($itemsViewer, $viewer['id']));
    }

    #[Test]
    public function dobavit_vladelcem_dobavlyaet_writer_i_povtor_otklonyaetsya(): void
    {
        $id = $this->sozdat();
        $writer = $this->sozdatAkkaunt();

        $data = $this->dannye($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
            'workspaceId' => $id,
            'accountId' => $writer['id'],
            'role' => 'writer',
        ]));

        $this->assertTrue($data['added']);
        $this->assertSame('writer', $this->rol($this->spisok($id), $writer['id']));

        $this->assertSame(
            'opencode.participant.already_exists',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
                'workspaceId' => $id,
                'accountId' => $writer['id'],
                'role' => 'viewer',
            ])),
        );
    }

    #[Test]
    public function dobavit_vladelca_i_rol_owner_zapreshcheny(): void
    {
        $id = $this->sozdat();

        $this->assertSame(
            'opencode.participant.already_owner',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
                'workspaceId' => $id,
                'accountId' => $this->accountId,
                'role' => 'viewer',
            ])),
        );

        $this->assertSame(
            'opencode.participant.owner_role_immutable',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
                'workspaceId' => $id,
                'accountId' => $this->sozdatAkkaunt()['id'],
                'role' => 'owner',
            ])),
        );
    }

    #[Test]
    public function dobavit_kandidata_bez_dostupa_k_proektu_otklonyaetsya(): void
    {
        $id = $this->sozdat();
        $kandidat = $this->sozdatAkkaunt();
        $this->projectami->zapretitDostup($kandidat['id']);

        $this->assertSame(
            'opencode.participant.project_access_denied',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
                'workspaceId' => $id,
                'accountId' => $kandidat['id'],
                'role' => 'viewer',
            ])),
        );
    }

    #[Test]
    public function dobavit_izmenit_udalit_ne_vladelcem_zapreshcheny(): void
    {
        $id = $this->sozdat();
        $writer = $this->sozdatAkkaunt();
        $target = $this->sozdatAkkaunt();
        $this->dobavit($id, $writer, 'writer');
        $this->dobavit($id, $target, 'viewer');

        $this->kak($this->emailDlya($writer));

        $this->assertSame(
            'opencode.session.forbidden',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
                'workspaceId' => $id,
                'accountId' => $this->sozdatAkkaunt()['id'],
                'role' => 'viewer',
            ])),
        );

        $this->assertSame(
            'opencode.session.forbidden',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/izmenit', [
                'workspaceId' => $id,
                'accountId' => $target['id'],
                'role' => 'writer',
            ])),
        );

        $this->assertSame(
            'opencode.session.forbidden',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/udalit', [
                'workspaceId' => $id,
                'accountId' => $target['id'],
            ])),
        );
    }

    #[Test]
    public function izmenit_perevodit_writer_v_viewer_i_obratno(): void
    {
        $id = $this->sozdat();
        $writer = $this->sozdatAkkaunt();
        $this->dobavit($id, $writer, 'writer');

        $this->assertTrue($this->izmenit($id, $writer, 'viewer'));
        $this->assertSame('viewer', $this->rol($this->spisok($id), $writer['id']));

        $this->assertTrue($this->izmenit($id, $writer, 'writer'));
        $this->assertSame('writer', $this->rol($this->spisok($id), $writer['id']));
    }

    #[Test]
    public function izmenit_ne_ponizhaet_vladelca(): void
    {
        $id = $this->sozdat();

        $this->assertSame(
            'opencode.participant.owner_role_immutable',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/izmenit', [
                'workspaceId' => $id,
                'accountId' => $this->accountId,
                'role' => 'viewer',
            ])),
        );
    }

    #[Test]
    public function udalit_udalyaet_writer_no_ne_vladelca(): void
    {
        $id = $this->sozdat();
        $writer = $this->sozdatAkkaunt();
        $this->dobavit($id, $writer, 'writer');

        $data = $this->dannye($this->zapros('POST', '/api/opencode/workspace/uchastniki/udalit', [
            'workspaceId' => $id,
            'accountId' => $writer['id'],
        ]));

        $this->assertTrue($data['removed']);
        $this->assertNull($this->rolIliNull($this->spisok($id), $writer['id']));

        $this->assertSame(
            'opencode.participant.owner_cannot_be_removed',
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/udalit', [
                'workspaceId' => $id,
                'accountId' => $this->accountId,
            ])),
        );
    }

    #[Test]
    public function spisok_neuchastnikom_skryvaet_sushchestvovanie(): void
    {
        $id = $this->sozdat();
        $this->kak($this->emailDlya($this->sozdatAkkaunt()));

        // Ключ `opencode.session.not_found` переведён, поэтому сравниваем
        // стабильный код либо его локализованное сообщение.
        $this->assertContains(
            $this->otkaz($this->zapros('POST', '/api/opencode/workspace/uchastniki/spisok', [
                'workspaceId' => $id,
            ])),
            ['opencode.session.not_found', 'Сессия OpenCode не найдена', 'The OpenCode session was not found'],
        );
    }

    #[Test]
    public function sostoyanie_otdayot_rol_uchastnikov_i_svoi_rekvizity(): void
    {
        $id = $this->sozdat();
        $viewer = $this->sozdatAkkaunt();
        $this->dobavit($id, $viewer, 'viewer');
        $this->perewestiStatus($id, 'RUNNING');

        $ownerState = $this->dannye($this->zapros('POST', '/api/opencode/workspace/sostoyanie', [
            'workspaceId' => $id,
        ]));

        $this->assertSame('owner', $ownerState['myRole']);
        $this->assertTrue($ownerState['canManage']);
        $this->assertTrue($ownerState['canAcceptMessages']);
        $this->assertCount(2, $ownerState['participants']);
        $this->assertSame($this->credentialId, $ownerState['myDefaultCredentialId']);
        $this->assertSame('gpt-4o', $ownerState['myDefaultModel']);

        $this->kak($this->emailDlya($viewer));

        $viewerState = $this->dannye($this->zapros('POST', '/api/opencode/workspace/sostoyanie', [
            'workspaceId' => $id,
        ]));

        $this->assertSame('viewer', $viewerState['myRole']);
        $this->assertFalse($viewerState['canManage']);
        $this->assertFalse($viewerState['canAcceptMessages']);
        $this->assertNull($viewerState['myDefaultCredentialId']);
    }

    #[Test]
    public function spisok_otdayot_rol_vladelca_i_priznak_obshchego(): void
    {
        $id = $this->sozdat();

        $svoi = $this->element($this->dannye($this->zapros('GET', '/api/opencode/workspace/spisok')), $id);

        $this->assertSame('owner', $svoi['myRole']);
        $this->assertSame($this->accountId, $svoi['ownerAccountId']);
        $this->assertSame(1, $svoi['participantsCount']);
        $this->assertFalse($svoi['shared']);

        $writer = $this->sozdatAkkaunt();
        $this->dobavit($id, $writer, 'writer');

        $obshchii = $this->element($this->dannye($this->zapros('GET', '/api/opencode/workspace/spisok')), $id);

        $this->assertSame('owner', $obshchii['myRole']);
        $this->assertSame($this->accountId, $obshchii['ownerAccountId']);
        $this->assertSame(2, $obshchii['participantsCount']);
        $this->assertTrue($obshchii['shared']);

        $this->kak($this->emailDlya($writer));

        $chuzhoi = $this->element($this->dannye($this->zapros('GET', '/api/opencode/workspace/spisok')), $id);

        $this->assertSame('writer', $chuzhoi['myRole']);
        $this->assertSame($this->accountId, $chuzhoi['ownerAccountId']);
        $this->assertTrue($chuzhoi['shared']);
    }

    private function sozdat(): string
    {
        $data = $this->dannye($this->zapros('POST', '/api/opencode/workspace/sozdat', [
            'name' => 'payments-refactor-' . uniqid('', true),
            'branch' => 'feature/payments',
            'projectIds' => ['project-1'],
            'prompt' => 'Первый промпт',
            'credentialId' => $this->credentialId,
        ]));

        $this->assertArrayHasKey('id', $data);

        return (string) $data['id'];
    }

    /**
     * @return array{id: string, email: string}
     */
    private function sozdatAkkaunt(): array
    {
        $id = 'acc-' . uniqid('', true);
        $email = uniqid('participant_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => $id,
            'email' => $email,
            'password' => 'pass',
            'roles' => 'ROLE_USER',
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        return ['id' => $id, 'email' => $email];
    }

    /**
     * @param array{id: string, email: string} $account
     */
    private function emailDlya(array $account): string
    {
        return $account['email'];
    }

    private function kak(string $email): void
    {
        $encoder = static::getContainer()->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        $this->client->setServerParameter(
            'HTTP_Authorization',
            'Bearer ' . $encoder->encode(['username' => $email, 'password' => 'pass']),
        );
    }

    /**
     * @param array{id: string, email: string} $account
     */
    private function dobavit(string $workspaceId, array $account, string $role): void
    {
        $data = $this->dannye($this->zapros('POST', '/api/opencode/workspace/uchastniki/dobavit', [
            'workspaceId' => $workspaceId,
            'accountId' => $account['id'],
            'role' => $role,
        ]));

        $this->assertTrue($data['added']);
    }

    private function izmenit(string $workspaceId, array $account, string $role): bool
    {
        $data = $this->dannye($this->zapros('POST', '/api/opencode/workspace/uchastniki/izmenit', [
            'workspaceId' => $workspaceId,
            'accountId' => $account['id'],
            'role' => $role,
        ]));

        return (bool) $data['updated'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spisok(string $workspaceId): array
    {
        $data = $this->dannye($this->zapros('POST', '/api/opencode/workspace/uchastniki/spisok', [
            'workspaceId' => $workspaceId,
        ]));

        $this->assertIsArray($data['items']);

        return $data['items'];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function rol(array $items, string $accountId): string
    {
        $role = $this->rolIliNull($items, $accountId);

        $this->assertIsString($role, 'Участник ' . $accountId . ' должен быть в списке');

        return $role;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function rolIliNull(array $items, string $accountId): ?string
    {
        foreach ($items as $item) {
            if (($item['accountId'] ?? null) === $accountId) {
                return (string) ($item['role'] ?? '');
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function element(array $items, string $workspaceId): array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $workspaceId) {
                return $item;
            }
        }

        $this->fail('Workspace ' . $workspaceId . ' не найден в списке');
    }

    private function perewestiStatus(string $id, string $status): void
    {
        $this->db->update('opencode_workspace', ['status' => $status], ['session_id' => $id]);
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

    /**
     * Повторяет формат ошибок соседних API-тестов: `fail`-конверт, HTTP 500,
     * код ошибки в `data.message`.
     *
     * @param array<string, mixed> $otvet
     */
    private function otkaz(array $otvet): string
    {
        $this->assertSame('fail', $otvet['status'] ?? null);
        $this->assertResponseStatusCodeSame(500);

        $message = $otvet['data']['message'] ?? null;

        $this->assertIsString($message);
        $this->assertNotSame('', $message);

        return $message;
    }
}
