<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Migration;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use App\OpenCode\Infra\Cli\WorkspaceMigrationCheckCommand;
use App\OpenCode\Infra\Migration\WorkspaceAggregateBackfill;
use App\OpenCode\Infra\Migration\WorkspaceAggregateParityChecker;
use App\OpenCode\Infra\Repository\SqlHodOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlSessiyaOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceMirror;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Интеграционные тесты миграции workspace-агрегата на реальной БД.
 *
 * Каждый тест работает в транзакции и откатывает её: данные dev-БД не
 * меняются. Если миграции ещё не применены, тест пропускается.
 */
final class WorkspaceAggregateMigrationTest extends KernelTestCase
{
    private Connection $db;

    private const NOW = 1_700_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->db = static::getContainer()->get(Connection::class);
        $this->db->beginTransaction();

        if (!$this->db->createSchemaManager()->tablesExist([
            'opencode_workspace_session',
            'opencode_workspace_turn',
            'opencode_workspace_notification',
            'opencode_workspace_participant',
            'opencode_workspace_pending_update',
        ])) {
            self::markTestSkipped('Expand-миграции workspace-агрегата ещё не применены.');
        }
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function backfill_perenosit_vse_polya_i_idempotenten(): void
    {
        $ws = $this->uid('ws');
        $this->vstavitWorkspace($ws, 'acc-owner-' . $ws, 'cred-' . $ws, 'Старый workspace');

        $this->db->insert('opencode_workspace_project', [
            'session_id' => $ws,
            'project_id' => 'p-1',
            'project_name' => 'api',
            'project_code' => 'services/api',
            'main_branch' => 'main',
            'branch' => 'feature/x',
            'can_push' => true,
            'position_index' => 0,
            'created_at' => self::NOW,
        ], ['can_push' => ParameterType::BOOLEAN]);

        $this->db->insert('opencode_session_participant', [
            'session_id' => $ws,
            'account_id' => 'acc-writer-' . $ws,
            'role' => 'writer',
            'default_credential_id' => null,
            'default_model' => null,
            'invited_by' => 'acc-owner-' . $ws,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $this->vstavitHod($ws, 'turn-1', 1, 'wf-1', 'ANSWERED', self::NOW);
        $this->vstavitHod($ws, 'turn-2', 2, 'wf-2', 'RUNNING', self::NOW + 1);

        $this->db->insert('opencode_workspace_update', [
            'session_id' => $ws,
            'workflow_id' => 'wf-upd',
            'payload' => json_encode(['kind' => 'project', 'projects' => [], 'files' => [], 'filesToDelete' => [], 'readOnly' => false]),
            'created_at' => self::NOW,
        ]);

        $this->vstavitUvedomlenie('n-1', 'acc-owner-' . $ws, $ws, 'turn-1', null);
        $this->vstavitUvedomlenie('n-2', 'acc-writer-' . $ws, $ws, 'turn-1', self::NOW + 100);

        $backfill = new WorkspaceAggregateBackfill($this->db);
        $backfill->run();

        $session = $this->db->fetchAssociative(
            'select * from opencode_workspace_session where id = :id',
            ['id' => $ws]
        );
        self::assertIsArray($session);
        self::assertSame('Старый workspace', $session['name']);
        self::assertSame('RUNNING', $session['status']);
        self::assertSame('feature/x', $session['branch']);

        $projects = $this->db->fetchAllAssociative(
            'select * from opencode_workspace_session_project where workspace_id = :id',
            ['id' => $ws]
        );
        self::assertCount(1, $projects);
        self::assertSame('main', $projects[0]['main_branch']);
        self::assertSame(0, (int) $projects[0]['position_index']);

        $participants = $this->db->fetchAllAssociative(
            'select account_id, role from opencode_workspace_participant where workspace_id = :id order by role',
            ['id' => $ws]
        );
        $roles = array_column($participants, 'role', 'account_id');
        self::assertSame('owner', $roles['acc-owner-' . $ws]);
        self::assertSame('writer', $roles['acc-writer-' . $ws]);

        $turns = $this->db->fetchAllAssociative(
            'select id, author_account_id, credential_id from opencode_workspace_turn where workspace_id = :id order by seq',
            ['id' => $ws]
        );
        self::assertCount(2, $turns);
        self::assertSame('acc-owner-' . $ws, $turns[0]['author_account_id']);
        self::assertSame('cred-' . $ws, $turns[0]['credential_id']);

        $notifications = $this->db->fetchAllAssociative(
            'select account_id, read_at from opencode_workspace_notification where workspace_id = :id order by account_id',
            ['id' => $ws]
        );
        self::assertCount(2, $notifications);
        $readByAccount = [];
        foreach ($notifications as $notification) {
            $readByAccount[$notification['account_id']] = $notification['read_at'];
        }
        self::assertNull($readByAccount['acc-owner-' . $ws]);
        self::assertSame(self::NOW + 100, (int) $readByAccount['acc-writer-' . $ws]);

        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_workspace_pending_update where workspace_id = :id',
            ['id' => $ws]
        ));

        $snapshot = $this->snapshot($ws);

        // Повторный прогон ничего не дублирует и не меняет.
        $backfill->run();

        self::assertSame($snapshot, $this->snapshot($ws), 'Повторный backfill должен быть идемпотентным');
    }

    #[Test]
    public function dubli_seq_perenumeruyutsya_tolko_v_celi(): void
    {
        $ws = $this->uid('ws');
        $this->vstavitWorkspace($ws, 'acc-' . $ws, 'cred-' . $ws, 'Дубли seq');

        $this->vstavitHod($ws, 'dup-a', 1, 'wf-a', 'ANSWERED', self::NOW);
        $this->vstavitHod($ws, 'dup-b', 1, 'wf-b', 'ANSWERED', self::NOW + 1);

        (new WorkspaceAggregateBackfill($this->db))->run();

        $legacy = $this->db->fetchFirstColumn(
            'select seq from opencode_turn where session_id = :id order by id',
            ['id' => $ws]
        );
        self::assertSame([1, 1], array_map('intval', $legacy), 'Легаси-нумерация не меняется');

        $target = $this->db->fetchAllAssociative(
            'select id, seq from opencode_workspace_turn where workspace_id = :id order by id',
            ['id' => $ws]
        );
        self::assertCount(2, $target);
        self::assertCount(2, array_unique(array_map(static fn (array $r): int => (int) $r['seq'], $target)));
        self::assertSame(2, $this->chislo(
            'select count(*) from opencode_workspace_migration_journal where workspace_id = :id',
            ['id' => $ws]
        ));

        $pervyyProgon = array_column($target, 'seq', 'id');
        (new WorkspaceAggregateBackfill($this->db))->run();
        $vtoroyProgon = array_column(
            $this->db->fetchAllAssociative(
                'select id, seq from opencode_workspace_turn where workspace_id = :id order by id',
                ['id' => $ws]
            ),
            'seq',
            'id'
        );
        self::assertSame($pervyyProgon, $vtoroyProgon, 'Перенумерация детерминирована');
    }

    #[Test]
    public function zerkalo_cherez_repozitorii_obnovlyaet_noviy_agregat(): void
    {
        $ws = $this->uid('ws');
        $this->vstavitWorkspace($ws, 'acc-' . $ws, 'cred-' . $ws, 'Зеркало');
        $this->vstavitHod($ws, 'turn-1', 1, 'wf-1', 'RUNNING', self::NOW);

        $mirror = new SqlWorkspaceMirror($this->db, new NullLogger(), true);

        // Создание хода через легаси-репозиторий зеркалится.
        $hody = new SqlHodOpenCodeRepository($this->db, $mirror);
        $hod = HodOpenCode::restore('turn-1', $ws, 1, 'wf-1', 'RUNNING', 'Привет', [], null, null, self::NOW, null);
        $hody->update($hod);

        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_workspace_turn where id = :id',
            ['id' => 'turn-1']
        ));

        // Завершение хода обновляет статус в новом агрегате.
        $zavershen = HodOpenCode::restore('turn-1', $ws, 1, 'wf-1', 'ANSWERED', 'Привет', [], null, null, self::NOW, self::NOW + 5);
        $hody->update($zavershen);

        $target = $this->db->fetchAssociative(
            'select status, finished_at from opencode_workspace_turn where id = :id',
            ['id' => 'turn-1']
        );
        self::assertSame('ANSWERED', $target['status']);
        self::assertSame(self::NOW + 5, (int) $target['finished_at']);

        // Уведомление и read_at.
        $uvedomleniya = new SqlUvedomleniyaOpenCodeRepository($this->db, $mirror);
        $uvedomleniya->save(new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            'acc-' . $ws,
            $ws,
            'turn-1',
            'Зеркало',
            'ANSWERED',
            'ANSWER',
            self::NOW,
            'workspace',
        ));

        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_workspace_notification where id = :id',
            ['id' => 'n-1']
        ));

        $uvedomleniya->zahvatitNeprochitannie('acc-' . $ws, ['n-1'], self::NOW + 7);

        $readAt = $this->db->fetchOne(
            'select read_at from opencode_workspace_notification where id = :id',
            ['id' => 'n-1']
        );
        self::assertSame(self::NOW + 7, (int) $readAt);

        // Удаление workspace зеркалится, легаси не трогается.
        $mirror->zerkalitSessiyu($ws);
        $mirror->udalitWorkspace($ws);

        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_workspace_session where id = :id',
            ['id' => $ws]
        ));
        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_session where id = :id',
            ['id' => $ws]
        ));
    }

    #[Test]
    public function oshibka_zerkala_ne_lomaet_legasi_operaciyu(): void
    {
        $ws = $this->uid('ws');
        $this->vstavitWorkspace($ws, 'acc-' . $ws, 'cred-' . $ws, 'Ошибка зеркала');

        $mirror = $this->createMock(WorkspaceMirror::class);
        $mirror->method('isEnabled')->willReturn(true);
        $mirror->method('zerkalitSessiyu')->willThrowException(new \RuntimeException('mirror down'));

        $repository = new SqlSessiyaOpenCodeRepository($this->db, $mirror);

        $rezultat = $repository->perevestiStatusWorkspace(
            $ws,
            [StatusWorkspace::RUNNING],
            StatusWorkspace::ARCHIVING,
        );

        self::assertTrue($rezultat, 'Легаси-переход статуса выполнен несмотря на ошибку зеркала');
        self::assertSame('ARCHIVING', (string) $this->db->fetchOne(
            'select status from opencode_workspace where session_id = :id',
            ['id' => $ws]
        ));
    }

    #[Test]
    public function gate_chist_na_sinhronnih_dannih_i_padaet_pri_rashozhdenii(): void
    {
        $ws = $this->uid('ws');
        $this->vstavitWorkspace($ws, 'acc-' . $ws, 'cred-' . $ws, 'Гейт');
        $this->vstavitHod($ws, 'turn-1', 1, 'wf-1', 'ANSWERED', self::NOW);
        $this->vstavitUvedomlenie('n-1', 'acc-' . $ws, $ws, 'turn-1', null);

        (new WorkspaceAggregateBackfill($this->db))->run();

        $checker = new WorkspaceAggregateParityChecker($this->db);
        self::assertTrue($checker->proverit()['ok']);

        $command = new WorkspaceMigrationCheckCommand($checker);
        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([]));

        // Расхождение в ключевом поле нового агрегата.
        $this->db->update('opencode_workspace_session', ['status' => 'ERROR'], ['id' => $ws]);

        $result = $checker->proverit();
        self::assertFalse($result['ok']);
        self::assertGreaterThan(0, $result['checks']['sessions']['mismatched']);

        $tester = new CommandTester(new WorkspaceMigrationCheckCommand($checker));
        self::assertSame(1, $tester->execute([]));
    }

    private function vstavitWorkspace(string $id, string $accountId, string $credentialId, string $title): void
    {
        $this->db->insert('opencode_session', [
            'id' => $id,
            'account_id' => $accountId,
            'credential_id' => $credentialId,
            'title' => $title,
            'tip' => 'workspace',
            'external_session_id' => null,
            'unit_id' => null,
            'unit_project_id' => null,
            'archived_at' => null,
            'archive_reason' => null,
            'model' => 'model-x',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $this->db->insert('opencode_workspace', [
            'session_id' => $id,
            'branch' => 'feature/x',
            'status' => 'RUNNING',
            'read_only' => false,
            'initial_prompt' => 'Первый промпт',
            'initial_prompt_started_at' => null,
            'error_key' => null,
            'operation' => 'start',
            'operation_workflow_id' => '',
            'operation_attempts' => 0,
            'next_attempt_at' => 0,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ], ['read_only' => ParameterType::BOOLEAN]);
    }

    private function vstavitHod(
        string $workspaceId,
        string $id,
        int $seq,
        string $workflowId,
        string $status,
        int $createdAt,
    ): void {
        $this->db->insert('opencode_turn', [
            'id' => $id,
            'session_id' => $workspaceId,
            'seq' => $seq,
            'workflow_id' => $workflowId,
            'status' => $status,
            'request_text' => 'Запрос',
            'answers_json' => '[]',
            'result_json' => null,
            'error_key' => null,
            'model' => 'model-x',
            'author_account_id' => '',
            'credential_id' => '',
            'author_display_name' => '',
            'created_at' => $createdAt,
            'finished_at' => null,
        ]);
    }

    private function vstavitUvedomlenie(
        string $id,
        string $accountId,
        string $workspaceId,
        string $turnId,
        ?int $readAt,
    ): void {
        $this->db->insert('opencode_notification', [
            'id' => $id,
            'account_id' => $accountId,
            'session_id' => $workspaceId,
            'turn_id' => $turnId,
            'session_title' => 'Заголовок',
            'status' => 'ANSWERED',
            'kind' => 'ANSWER',
            'created_at' => self::NOW,
            'read_at' => $readAt,
            'session_type' => 'unit',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function snapshot(string $workspaceId): array
    {
        return [
            'session' => $this->chislo('select count(*) from opencode_workspace_session where id = :id', ['id' => $workspaceId]),
            'projects' => $this->chislo('select count(*) from opencode_workspace_session_project where workspace_id = :id', ['id' => $workspaceId]),
            'participants' => $this->chislo('select count(*) from opencode_workspace_participant where workspace_id = :id', ['id' => $workspaceId]),
            'turns' => $this->chislo('select count(*) from opencode_workspace_turn where workspace_id = :id', ['id' => $workspaceId]),
            'notifications' => $this->chislo('select count(*) from opencode_workspace_notification where workspace_id = :id', ['id' => $workspaceId]),
            'pending' => $this->chislo('select count(*) from opencode_workspace_pending_update where workspace_id = :id', ['id' => $workspaceId]),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function chislo(string $sql, array $params = []): int
    {
        return (int) $this->db->fetchOne($sql, $params);
    }

    private function uid(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(8));
    }
}
