<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Migration;

use App\OpenCode\Infra\Cli\WorkspaceMigrationContractCommand;
use App\OpenCode\Infra\Migration\WorkspaceAggregateBackfill;
use App\OpenCode\Infra\Migration\WorkspaceAggregateContract;
use App\OpenCode\Infra\Migration\WorkspaceAggregateParityChecker;
use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Contract-шаг на реальной тестовой БД.
 *
 * Каждый тест выполняется под транзакцией и откатывает её: dev-данные не
 * меняются. Force-удаление проверяется только внутри этой транзакции.
 */
final class WorkspaceAggregateContractTest extends KernelTestCase
{
    private const NOW = 1_700_000_001;

    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->db = static::getContainer()->get(Connection::class);
        $this->db->beginTransaction();

        if (!$this->db->createSchemaManager()->tablesExist([
            'opencode_workspace_session',
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
    public function dry_run_pokazyvaet_plan_i_ne_menyaet_dannye(): void
    {
        $ws = $this->uid('ws-contract-dry');
        $this->vstavitWorkspace($ws);
        (new WorkspaceAggregateBackfill($this->db))->run();

        $before = $this->chislo('select count(*) from opencode_session where tip = :tip', ['tip' => 'workspace']);

        $command = new WorkspaceMigrationContractCommand(
            $this->db,
            $this->contract(),
        );
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Dry-run', $tester->getDisplay());

        $after = $this->chislo('select count(*) from opencode_session where tip = :tip', ['tip' => 'workspace']);

        self::assertSame($before, $after, 'Dry-run не должен менять данные');
        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_session where id = :id',
            ['id' => $ws],
        ));
    }

    #[Test]
    public function force_udalyaet_tolko_workspace_i_ne_trogaet_unit(): void
    {
        $ws = $this->uid('ws-contract-force');
        $unit = $this->uid('ws-contract-unit');

        $this->vstavitWorkspace($ws);
        $this->vstavitUnitSession($unit);

        $this->db->insert('opencode_workspace_project', [
            'session_id' => $ws,
            'project_id' => 'p-contract',
            'project_name' => 'api',
            'project_code' => 'services/api',
            'main_branch' => 'main',
            'branch' => 'feature/x',
            'can_push' => true,
            'position_index' => 0,
            'created_at' => self::NOW,
        ], ['can_push' => ParameterType::BOOLEAN]);

        $this->db->insert('opencode_workspace_unit', [
            'workspace_id' => $ws,
            'unit_id' => 'unit-contract',
            'project_id' => 'p-contract',
            'unit_name' => 'unit',
            'branch' => 'feature/x',
            'created_at' => self::NOW,
        ]);

        $this->db->insert('opencode_session_participant', [
            'session_id' => $ws,
            'account_id' => 'acc-contract-writer',
            'role' => 'writer',
            'default_credential_id' => null,
            'default_model' => null,
            'invited_by' => null,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $this->vstavitHod($ws, $this->uid('turn-contract'));
        $this->vstavitUvedomlenie($this->uid('notif-contract'), $ws);

        (new WorkspaceAggregateBackfill($this->db))->run();

        $contract = $this->contract();

        self::assertSame([], $contract->proveritBezopasnost(), 'Гейт должен быть чист');

        $report = $contract->run();

        self::assertArrayHasKey('opencode_session', $report);
        self::assertArrayHasKey('opencode_workspace', $report);

        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_session where id = :id',
            ['id' => $ws],
        ));
        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_workspace where session_id = :id',
            ['id' => $ws],
        ));
        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_workspace_project where session_id = :id',
            ['id' => $ws],
        ));
        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_workspace_unit where workspace_id = :id',
            ['id' => $ws],
        ));
        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_turn where session_id = :id',
            ['id' => $ws],
        ));
        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_notification where session_id = :id',
            ['id' => $ws],
        ));
        self::assertSame(0, $this->chislo(
            'select count(*) from opencode_session_participant where session_id = :id',
            ['id' => $ws],
        ));

        // Unit-сессия и новый агрегат остаются.
        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_session where id = :id and tip = :tip',
            ['id' => $unit, 'tip' => 'unit'],
        ));
        self::assertSame(1, $this->chislo(
            'select count(*) from opencode_workspace_session where id = :id',
            ['id' => $ws],
        ));
        // Writer из легаси + гарантированная строка owner.
        self::assertSame(2, $this->chislo(
            'select count(*) from opencode_workspace_participant where workspace_id = :id',
            ['id' => $ws],
        ));

        // Union уведомлений работает при пустой легаси-таблице: непрочитанное
        // берётся из нового агрегата.
        $notifications = (new SqlUvedomleniyaOpenCodeRepository($this->db))
            ->findNeprochitannie('acc-' . $ws, 10);

        self::assertNotEmpty($notifications);
    }

    private function contract(): WorkspaceAggregateContract
    {
        return new WorkspaceAggregateContract(
            $this->db,
            new WorkspaceAggregateParityChecker($this->db),
        );
    }

    private function vstavitWorkspace(string $id): void
    {
        $this->db->insert('opencode_session', [
            'id' => $id,
            'account_id' => 'acc-' . $id,
            'credential_id' => 'cred-' . $id,
            'title' => 'Contract workspace',
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

    private function vstavitUnitSession(string $id): void
    {
        $this->db->insert('opencode_session', [
            'id' => $id,
            'account_id' => 'acc-' . $id,
            'credential_id' => 'cred-' . $id,
            'title' => 'Unit session',
            'tip' => 'unit',
            'external_session_id' => null,
            'unit_id' => null,
            'unit_project_id' => null,
            'archived_at' => null,
            'archive_reason' => null,
            'model' => 'model-x',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    private function vstavitHod(string $workspaceId, string $id): void
    {
        $this->db->insert('opencode_turn', [
            'id' => $id,
            'session_id' => $workspaceId,
            'seq' => 1,
            'workflow_id' => 'wf-' . $id,
            'status' => 'ANSWERED',
            'request_text' => 'Запрос',
            'answers_json' => '[]',
            'result_json' => null,
            'error_key' => null,
            'model' => 'model-x',
            'author_account_id' => '',
            'credential_id' => '',
            'author_display_name' => '',
            'created_at' => self::NOW,
            'finished_at' => null,
        ]);
    }

    private function vstavitUvedomlenie(string $id, string $workspaceId): void
    {
        $this->db->insert('opencode_notification', [
            'id' => $id,
            'account_id' => 'acc-' . $workspaceId,
            'session_id' => $workspaceId,
            'turn_id' => 'turn-' . $id,
            'session_title' => 'Заголовок',
            'status' => 'ANSWERED',
            'kind' => 'ANSWER',
            'created_at' => self::NOW,
            'read_at' => null,
            'session_type' => 'unit',
        ]);
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
