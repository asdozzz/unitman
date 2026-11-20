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
 * HTTP-контракт workspace-маршрутов.
 *
 * Temporal не поднимается: порт Runner'а и порт проектов Unitman подменены.
 * JWT проверяется по-настоящему, поэтому в БД создаётся реальный аккаунт.
 */
final class WorkspaceControllerTest extends WebTestCase
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
        $this->email = uniqid('workspace_api_', true) . '@asd.ru';

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

    #[Test]
    public function sozdanie_vozvrashchaet_id_i_status_i_popadaet_v_spisok(): void
    {
        $id = $this->sozdat();

        $this->assertSame(
            'workspace',
            (string) $this->db->fetchOne('select tip from opencode_session where id = ?', [$id]),
        );
        $this->assertSame(
            'CREATING',
            (string) $this->db->fetchOne('select status from opencode_workspace where session_id = ?', [$id]),
        );

        $spisok = $this->dannye($this->zapros('GET', '/api/opencode/workspace/spisok?onlyActive=true'));

        $this->assertIsArray($spisok);

        $nashli = null;

        foreach ($spisok as $element) {
            if (($element['id'] ?? null) === $id) {
                $nashli = $element;
            }
        }

        $this->assertIsArray($nashli);
        $this->assertSame('payments-refactor', $nashli['name']);
        $this->assertSame('CREATING', $nashli['status']);
        $this->assertSame(
            'https://47000.payments-refactor-workspac.workspaces.example.test',
            $nashli['ideLink'],
        );
    }

    #[Test]
    public function sostoyanie_pokazivaet_can_accept_messages(): void
    {
        $id = $this->sozdat();

        $state = $this->dannye($this->zapros('POST', '/api/opencode/workspace/sostoyanie', [
            'workspaceId' => $id,
        ]));

        $this->assertSame('CREATING', $state['status']);
        $this->assertFalse($state['canAcceptMessages']);
        $this->assertNull($state['lastTurnId']);
        $this->assertSame('project-1', $state['projects'][0]['projectId']);
    }

    #[Test]
    public function prompt_v_creating_blokiruetsya(): void
    {
        $id = $this->sozdat();

        $otvet = $this->zapros('POST', '/api/opencode/prompt', [
            'sessionId' => $id,
            'text' => 'Привет',
        ]);

        $this->assertSame('fail', $otvet['status']);
    }

    #[Test]
    public function arhivaciya_trebuet_podtverzhdeniya_pri_gryaznyh_proektah_a_force_zapuskaet_stop(): void
    {
        $id = $this->sozdat();
        $this->perewestiStatus($id, 'RUNNING');

        $this->manager->zadatRezultatProverki(true, [
            ['projectId' => 'project-1', 'name' => 'api', 'dirty' => true, 'files' => [' M src/Foo.php']],
        ]);

        $bezForce = $this->dannye($this->zapros('POST', '/api/opencode/workspace/arhivirovat', [
            'workspaceId' => $id,
        ]));

        $this->assertTrue($bezForce['requiresConfirmation']);
        $this->assertSame('project-1', $bezForce['dirtyProjects'][0]['projectId']);

        $sForce = $this->dannye($this->zapros('POST', '/api/opencode/workspace/arhivirovat', [
            'workspaceId' => $id,
            'force' => true,
        ]));

        $this->assertFalse($sForce['requiresConfirmation']);
        $this->assertSame('ARCHIVING', $sForce['status']);
        $this->assertSame(
            'stop',
            (string) $this->db->fetchOne('select operation from opencode_workspace where session_id = ?', [$id]),
        );
    }

    #[Test]
    public function vosstanovlenie_i_udalenie_menyayut_status(): void
    {
        $id = $this->sozdat();
        $this->perewestiStatus($id, 'ARCHIVED', true);

        $vosstanovlenie = $this->dannye($this->zapros('POST', '/api/opencode/workspace/vosstanovit', [
            'workspaceId' => $id,
        ]));

        $this->assertSame('STARTING', $vosstanovlenie['status']);

        $this->perewestiStatus($id, 'RUNNING');

        $otvet = $this->zapros('POST', '/api/opencode/workspace/udalit', ['workspaceId' => $id]);

        $this->assertSame('success', $otvet['status']);
        $this->assertNull($otvet['data']);
        $this->assertSame(
            'DELETING',
            (string) $this->db->fetchOne('select status from opencode_workspace where session_id = ?', [$id]),
        );
    }

    private function sozdat(): string
    {
        $otvet = $this->zapros('POST', '/api/opencode/workspace/sozdat', [
            'name' => 'payments-refactor',
            'branch' => 'feature/payments',
            'projectIds' => ['project-1'],
            'prompt' => 'Первый промпт',
            'credentialId' => $this->credentialId,
        ]);

        $data = $this->dannye($otvet);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('CREATING', $data['status']);

        return (string) $data['id'];
    }

    private function perewestiStatus(string $id, string $status, bool $archive = false): void
    {
        $this->db->update('opencode_workspace', ['status' => $status], ['session_id' => $id]);

        if ($archive) {
            $this->db->update(
                'opencode_session',
                ['archived_at' => time(), 'archive_reason' => 'manual'],
                ['id' => $id],
            );
        }
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
}
