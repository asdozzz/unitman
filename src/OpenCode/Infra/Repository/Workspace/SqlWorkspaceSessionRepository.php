<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository\Workspace;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Workspace\Model\WorkspaceFilter;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlWorkspaceSessionRepository implements WorkspaceSessionRepository
{
    private const TABLE = 'opencode_workspace_session';

    private const PROJECT_TABLE = 'opencode_workspace_session_project';

    private const PARTICIPANT_TABLE = 'opencode_workspace_participant';

    private const PENDING_TABLE = 'opencode_workspace_pending_update';

    private const UNIT_TABLE = 'opencode_workspace_unit';

    private const TURN_TABLE = 'opencode_workspace_turn';

    private const NOTIFICATION_TABLE = 'opencode_workspace_notification';

    private const LIKE_ESCAPE_CHAR = '\\';

    public function __construct(private Connection $connection)
    {
    }

    public function save(WorkspaceSession $workspace): void
    {
        $this->connection->transactional(function () use ($workspace): void {
            $this->insertOrUpdate($workspace);
            $this->synchronizirovatProekty($workspace);
            $this->synchronizirovatUniti($workspace);
        });
    }

    public function update(WorkspaceSession $workspace): void
    {
        $this->connection->transactional(function () use ($workspace): void {
            $this->insertOrUpdate($workspace);
            $this->synchronizirovatProekty($workspace);
            $this->synchronizirovatUniti($workspace);
        });
    }

    public function getById(string $id): WorkspaceSession
    {
        $workspace = $this->findById($id);

        if ($workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        return $workspace;
    }

    public function findById(string $id): ?WorkspaceSession
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where id = :id",
            ['id' => $id]
        );

        if (empty($row)) {
            return null;
        }

        return $this->makeWorkspace($row);
    }

    public function findByAccountId(string $accountId, WorkspaceFilter $filter): array
    {
        $conditions = ['s.account_id = :account_id'];
        $params = ['account_id' => $accountId];
        $types = ['account_id' => ParameterType::STRING];

        if ($filter->onlyActive) {
            $conditions[] = 's.archived_at is null';
        }

        if ($filter->estPoisk()) {
            $conditions[] = 's.name like :search escape ' . self::quoteEscapeChar();
            $params['search'] = '%' . self::escapeLike(trim((string) $filter->search)) . '%';
            $types['search'] = ParameterType::STRING;
        }

        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select s.* from $table s where " . implode(' and ', $conditions) . ' order by s.updated_at desc, s.id',
            $params,
            $types
        );

        return $this->makeWorkspaces($rows);
    }

    public function findByAccountIdWithParticipation(string $accountId, WorkspaceFilter $filter): array
    {
        $conditions = [
            '(s.account_id = :account_id
              or exists (
                  select 1 from ' . self::PARTICIPANT_TABLE . ' p
                  where p.workspace_id = s.id and p.account_id = :account_id
              ))',
        ];
        $params = ['account_id' => $accountId];
        $types = ['account_id' => ParameterType::STRING];

        if ($filter->onlyActive) {
            $conditions[] = 's.archived_at is null';
        }

        if ($filter->estPoisk()) {
            $conditions[] = 's.name like :search escape ' . self::quoteEscapeChar();
            $params['search'] = '%' . self::escapeLike(trim((string) $filter->search)) . '%';
            $types['search'] = ParameterType::STRING;
        }

        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select s.* from $table s where " . implode(' and ', $conditions)
            . ' order by s.updated_at desc, s.id',
            $params,
            $types
        );

        return $this->makeWorkspaces($rows);
    }

    public function findAktivnie(int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where archived_at is null order by updated_at desc, id limit :limit",
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER]
        );

        return $this->makeWorkspaces($rows);
    }

    public function findAktivnieVTranzite(int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table
             where archived_at is null
                or status in ('CREATING', 'STARTING', 'ARCHIVING', 'DELETING')
             order by updated_at desc, id limit :limit",
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER]
        );

        return $this->makeWorkspaces($rows);
    }

    public function perevestiStatus(string $workspaceId, array $from, StatusWorkspace $to): bool
    {
        if ($from === []) {
            return false;
        }

        $table = self::TABLE;
        $fromValues = array_map(static fn (StatusWorkspace $status): string => $status->value, $from);

        $affected = $this->connection->executeStatement(
            "update $table set status = :to, updated_at = :now
             where id = :id and status in (:from)",
            [
                'to' => $to->value,
                'now' => time(),
                'id' => $workspaceId,
                'from' => $fromValues,
            ],
            [
                'to' => ParameterType::STRING,
                'now' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
                'from' => \Doctrine\DBAL\ArrayParameterType::STRING,
            ]
        );

        return $affected > 0;
    }

    public function delete(string $workspaceId): void
    {
        $this->connection->transactional(function () use ($workspaceId): void {
            foreach ([
                self::NOTIFICATION_TABLE,
                self::PARTICIPANT_TABLE,
                self::PENDING_TABLE,
                self::UNIT_TABLE,
                self::TURN_TABLE,
                self::PROJECT_TABLE,
            ] as $table) {
                $this->connection->delete($table, ['workspace_id' => $workspaceId]);
            }

            $this->connection->delete(self::TABLE, ['id' => $workspaceId]);
        });
    }

    public function savePendingUpdate(string $workspaceId, string $workflowId, array $payload, int $createdAt): void
    {
        $this->connection->executeStatement(
            'insert into ' . self::PENDING_TABLE . ' (workspace_id, workflow_id, payload, created_at) '
            . 'values (:workspace_id, :workflow_id, :payload, :created_at) '
            . 'on conflict (workspace_id) do update set '
            . 'workflow_id = excluded.workflow_id, payload = excluded.payload, created_at = excluded.created_at',
            [
                'workspace_id' => $workspaceId,
                'workflow_id' => $workflowId,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $createdAt,
            ],
            [
                'workspace_id' => ParameterType::STRING,
                'workflow_id' => ParameterType::STRING,
                'payload' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ]
        );
    }

    public function findPendingUpdate(string $workspaceId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'select workflow_id, payload, created_at from ' . self::PENDING_TABLE . ' where workspace_id = :id',
            ['id' => $workspaceId]
        );

        if (empty($row)) {
            return null;
        }

        $payload = json_decode((string) $row['payload'], true);

        return [
            'workflowId' => (string) $row['workflow_id'],
            'payload' => is_array($payload) ? $payload : [],
            'createdAt' => (int) $row['created_at'],
        ];
    }

    public function deletePendingUpdate(string $workspaceId): void
    {
        $this->connection->delete(self::PENDING_TABLE, ['workspace_id' => $workspaceId]);
    }

    public function findPendingWorkspaceIds(array $workspaceIds): array
    {
        if ($workspaceIds === []) {
            return [];
        }

        $rows = $this->connection->fetchFirstColumn(
            'select workspace_id from ' . self::PENDING_TABLE . ' where workspace_id in (:ids)',
            ['ids' => $workspaceIds],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $result = [];

        foreach ($rows as $id) {
            $result[(string) $id] = true;
        }

        return $result;
    }

    private function insertOrUpdate(WorkspaceSession $workspace): void
    {
        $state = $workspace->getWorkspace();

        $this->connection->executeStatement(
            'insert into ' . self::TABLE . ' ('
            . 'id, account_id, credential_id, name, branch, status, read_only, initial_prompt, '
            . 'initial_prompt_started_at, error_key, operation, operation_workflow_id, '
            . 'operation_attempts, next_attempt_at, external_session_id, archived_at, '
            . 'archive_reason, model, created_at, updated_at'
            . ') values ('
            . ':id, :account_id, :credential_id, :name, :branch, :status, :read_only, :initial_prompt, '
            . ':initial_prompt_started_at, :error_key, :operation, :operation_workflow_id, '
            . ':operation_attempts, :next_attempt_at, :external_session_id, :archived_at, '
            . ':archive_reason, :model, :created_at, :updated_at'
            . ') on conflict (id) do update set '
            . 'credential_id = excluded.credential_id, '
            . 'name = excluded.name, '
            . 'branch = excluded.branch, '
            . 'status = excluded.status, '
            . 'read_only = excluded.read_only, '
            . 'initial_prompt = excluded.initial_prompt, '
            . 'initial_prompt_started_at = excluded.initial_prompt_started_at, '
            . 'error_key = excluded.error_key, '
            . 'operation = excluded.operation, '
            . 'operation_workflow_id = excluded.operation_workflow_id, '
            . 'operation_attempts = excluded.operation_attempts, '
            . 'next_attempt_at = excluded.next_attempt_at, '
            . 'external_session_id = excluded.external_session_id, '
            . 'archived_at = excluded.archived_at, '
            . 'archive_reason = excluded.archive_reason, '
            . 'model = excluded.model, '
            . 'updated_at = excluded.updated_at',
            [
                'id' => $workspace->getId(),
                'account_id' => $workspace->getAccountId(),
                'credential_id' => $workspace->getCredentialId(),
                'name' => $workspace->getName(),
                'branch' => $state->getBranch(),
                'status' => $state->getStatus()->value,
                'read_only' => $state->isReadOnly(),
                'initial_prompt' => $state->getInitialPrompt(),
                'initial_prompt_started_at' => $state->getInitialPromptStartedAt(),
                'error_key' => $state->getErrorKey(),
                'operation' => $state->getOperation(),
                'operation_workflow_id' => $state->getOperationWorkflowId(),
                'operation_attempts' => $state->getOperationAttempts(),
                'next_attempt_at' => $state->getNextAttemptAt(),
                'external_session_id' => $workspace->getExternalSessionId(),
                'archived_at' => $workspace->getArchivedAt(),
                'archive_reason' => $workspace->getArchiveReason(),
                'model' => $workspace->getModel(),
                'created_at' => $workspace->getCreatedAt(),
                'updated_at' => $workspace->getUpdatedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'credential_id' => ParameterType::STRING,
                'name' => ParameterType::STRING,
                'branch' => ParameterType::STRING,
                'status' => ParameterType::STRING,
                'read_only' => ParameterType::BOOLEAN,
                'initial_prompt' => ParameterType::STRING,
                'initial_prompt_started_at' => ParameterType::INTEGER,
                'error_key' => ParameterType::STRING,
                'operation' => ParameterType::STRING,
                'operation_workflow_id' => ParameterType::STRING,
                'operation_attempts' => ParameterType::INTEGER,
                'next_attempt_at' => ParameterType::INTEGER,
                'external_session_id' => ParameterType::STRING,
                'archived_at' => ParameterType::INTEGER,
                'archive_reason' => ParameterType::STRING,
                'model' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ]
        );
    }

    private function synchronizirovatUniti(WorkspaceSession $workspace): void
    {
        $workspaceId = $workspace->getId();

        $this->connection->delete(self::UNIT_TABLE, ['workspace_id' => $workspaceId]);

        foreach ($workspace->getWorkspace()->getUnits() as $unit) {
            $this->connection->insert(
                self::UNIT_TABLE,
                [
                    'workspace_id' => $workspaceId,
                    'unit_id' => $unit->unitId,
                    'project_id' => $unit->projectId,
                    'unit_name' => $unit->unitName,
                    'branch' => $unit->branch,
                    'created_at' => $workspace->getUpdatedAt(),
                ],
                [
                    'workspace_id' => ParameterType::STRING,
                    'unit_id' => ParameterType::STRING,
                    'project_id' => ParameterType::STRING,
                    'unit_name' => ParameterType::STRING,
                    'branch' => ParameterType::STRING,
                    'created_at' => ParameterType::INTEGER,
                ]
            );
        }
    }

    private function synchronizirovatProekty(WorkspaceSession $workspace): void
    {
        $workspaceId = $workspace->getId();

        $this->connection->delete(self::PROJECT_TABLE, ['workspace_id' => $workspaceId]);

        foreach ($workspace->getWorkspace()->getProjects() as $project) {
            $this->connection->insert(
                self::PROJECT_TABLE,
                [
                    'workspace_id' => $workspaceId,
                    'project_id' => $project->projectId,
                    'project_name' => $project->projectName,
                    'project_code' => $project->projectCode,
                    'main_branch' => $project->mainBranch,
                    'branch' => $project->branch,
                    'can_push' => $project->canPush,
                    'position_index' => $project->positionIndex,
                    'created_at' => $workspace->getCreatedAt(),
                ],
                [
                    'workspace_id' => ParameterType::STRING,
                    'project_id' => ParameterType::STRING,
                    'project_name' => ParameterType::STRING,
                    'project_code' => ParameterType::STRING,
                    'main_branch' => ParameterType::STRING,
                    'branch' => ParameterType::STRING,
                    'can_push' => ParameterType::BOOLEAN,
                    'position_index' => ParameterType::INTEGER,
                    'created_at' => ParameterType::INTEGER,
                ]
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<WorkspaceSession>
     */
    private function makeWorkspaces(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $projectRows = $this->connection->fetchAllAssociative(
            'select * from ' . self::PROJECT_TABLE . ' where workspace_id in (:ids) order by workspace_id, position_index',
            ['ids' => $ids],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $projects = [];

        foreach ($projectRows as $projectRow) {
            $workspaceId = (string) $projectRow['workspace_id'];
            $projects[$workspaceId] ??= [];
            $projects[$workspaceId][] = new ProektWorkspace(
                (string) $projectRow['project_id'],
                (string) $projectRow['project_name'],
                (string) $projectRow['project_code'],
                self::toBool($projectRow['can_push'] ?? false),
                (int) ($projectRow['position_index'] ?? 0),
                (string) ($projectRow['main_branch'] ?? ''),
                (string) ($projectRow['branch'] ?? ''),
            );
        }

        $unitRows = $this->connection->fetchAllAssociative(
            'select * from ' . self::UNIT_TABLE . ' where workspace_id in (:ids) order by workspace_id, created_at, unit_id',
            ['ids' => $ids],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $units = [];

        foreach ($unitRows as $unitRow) {
            $workspaceId = (string) $unitRow['workspace_id'];
            $units[$workspaceId] ??= [];
            $units[$workspaceId][] = new WorkspaceUnit(
                (string) $unitRow['unit_id'],
                (string) $unitRow['project_id'],
                (string) $unitRow['unit_name'],
                (string) ($unitRow['branch'] ?? ''),
            );
        }

        $result = [];

        foreach ($rows as $row) {
            $row['_projects'] = $projects[(string) $row['id']] ?? [];
            $row['_units'] = $units[(string) $row['id']] ?? [];
            $result[] = $this->makeWorkspace($row);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeWorkspace(array $row): WorkspaceSession
    {
        $projects = $row['_projects'] ?? null;

        if (!is_array($projects)) {
            $workspaceId = (string) $row['id'];
            $projectRows = $this->connection->fetchAllAssociative(
                'select * from ' . self::PROJECT_TABLE . ' where workspace_id = :id order by position_index',
                ['id' => $workspaceId]
            );

            $projects = [];

            foreach ($projectRows as $projectRow) {
                $projects[] = new ProektWorkspace(
                    (string) $projectRow['project_id'],
                    (string) $projectRow['project_name'],
                    (string) $projectRow['project_code'],
                    self::toBool($projectRow['can_push'] ?? false),
                    (int) ($projectRow['position_index'] ?? 0),
                    (string) ($projectRow['main_branch'] ?? ''),
                    (string) ($projectRow['branch'] ?? ''),
                );
            }
        }

        $units = $row['_units'] ?? null;

        if (!is_array($units)) {
            $workspaceId = (string) $row['id'];
            $unitRows = $this->connection->fetchAllAssociative(
                'select * from ' . self::UNIT_TABLE . ' where workspace_id = :id order by created_at, unit_id',
                ['id' => $workspaceId]
            );

            $units = [];

            foreach ($unitRows as $unitRow) {
                $units[] = new WorkspaceUnit(
                    (string) $unitRow['unit_id'],
                    (string) $unitRow['project_id'],
                    (string) $unitRow['unit_name'],
                    (string) ($unitRow['branch'] ?? ''),
                );
            }
        }

        return WorkspaceSession::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['credential_id'],
            (string) $row['name'],
            self::nullableString($row['external_session_id'] ?? null),
            self::nullableInt($row['archived_at'] ?? null),
            self::nullableString($row['archive_reason'] ?? null),
            Workspace::restore(
                (string) $row['branch'],
                (string) $row['initial_prompt'],
                $projects,
                StatusWorkspace::izStroki((string) $row['status']),
                self::toBool($row['read_only'] ?? false),
                self::nullableString($row['error_key'] ?? null),
                (string) $row['operation'],
                (string) $row['operation_workflow_id'],
                (int) ($row['operation_attempts'] ?? 0),
                (int) ($row['next_attempt_at'] ?? 0),
                self::nullableInt($row['initial_prompt_started_at'] ?? null),
                (int) $row['created_at'],
                (int) $row['updated_at'],
                $units,
            ),
            (int) $row['created_at'],
            (int) $row['updated_at'],
            self::nullableString($row['model'] ?? null),
        );
    }

    private static function quoteEscapeChar(): string
    {
        return "'" . self::LIKE_ESCAPE_CHAR . "'";
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE_CHAR, '%', '_'],
            [self::LIKE_ESCAPE_CHAR . self::LIKE_ESCAPE_CHAR, self::LIKE_ESCAPE_CHAR . '%', self::LIKE_ESCAPE_CHAR . '_'],
            $value
        );
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 't', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
