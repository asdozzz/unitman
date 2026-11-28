<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class SqlSessiyaOpenCodeRepository implements SessiyaOpenCodeRepository
{
    private const TABLE = 'opencode_session';

    private const UNITS_TABLE = 'opencode_session_unit';

    private const WORKSPACE_TABLE = 'opencode_workspace';

    private const WORKSPACE_PROJECT_TABLE = 'opencode_workspace_project';

    private const WORKSPACE_UNIT_TABLE = 'opencode_workspace_unit';

    private const WORKSPACE_UPDATE_TABLE = 'opencode_workspace_update';

    private const TURN_TABLE = 'opencode_turn';

    private const NOTIFICATION_TABLE = 'opencode_notification';

    private const LIKE_ESCAPE_CHAR = '\\';

    public function __construct(
        private Connection $connection,
        private ?WorkspaceMirror $mirror = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function save(SessiyaOpenCode $session): void
    {
        $this->connection->transactional(function () use ($session): void {
            $this->connection->insert(
                self::TABLE,
                $this->sessionRow($session),
                $this->sessionTypes()
            );

            $this->synchronizirovatPrivyazki($session);
            $this->sohranitWorkspace($session);
        });

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitSessiyu($session->getId()));
    }

    public function update(SessiyaOpenCode $session): void
    {
        $this->connection->transactional(function () use ($session): void {
            $this->connection->update(
                self::TABLE,
                [
                    'credential_id' => $session->getCredentialId(),
                    'title' => $session->getTitle(),
                    'tip' => $session->getTip()->value,
                    'external_session_id' => $session->getExternalSessionId(),
                    'unit_id' => $session->getUnitId(),
                    'unit_project_id' => $session->getUnitProjectId(),
                    'archived_at' => $session->getArchivedAt(),
                    'archive_reason' => $session->getArchiveReason(),
                    'model' => $session->getModel(),
                    'updated_at' => $session->getUpdatedAt(),
                ],
                ['id' => $session->getId()],
                [
                    'credential_id' => ParameterType::STRING,
                    'title' => ParameterType::STRING,
                    'tip' => ParameterType::STRING,
                    'external_session_id' => ParameterType::STRING,
                    'unit_id' => ParameterType::STRING,
                    'unit_project_id' => ParameterType::STRING,
                    'archived_at' => ParameterType::INTEGER,
                    'archive_reason' => ParameterType::STRING,
                    'model' => ParameterType::STRING,
                    'updated_at' => ParameterType::INTEGER,
                    'id' => ParameterType::STRING,
                ]
            );

            $this->synchronizirovatPrivyazki($session);
            $this->sohranitWorkspace($session);
        });

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitSessiyu($session->getId()));
    }

    public function getById(string $id): SessiyaOpenCode
    {
        $session = $this->findById($id);

        if ($session === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        return $session;
    }

    public function findById(string $id): ?SessiyaOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE id = :id",
            ['id' => $id]
        );

        if (empty($row)) {
            return null;
        }

        return $this->makeSessions([$row])[0] ?? null;
    }

    public function findByAccountId(string $accountId, SessiyaOpenCodeFilter $filter): array
    {
        $table = self::TABLE;
        $conditions = ['account_id = :account_id'];
        $params = ['account_id' => $accountId];
        $types = ['account_id' => ParameterType::STRING];

        if ($filter->isOnlyActive()) {
            $conditions[] = 'archived_at is null';
        }

        $type = $filter->getType();

        if ($type !== null) {
            $conditions[] = 'tip = :tip';
            $params['tip'] = $type->value;
            $types['tip'] = ParameterType::STRING;
        }

        if ($filter->estPoisk()) {
            $conditions[] = 'title like :search escape ' . self::quoteEscapeChar();
            $params['search'] = '%' . self::escapeLike($filter->getSearch() ?? '') . '%';
            $types['search'] = ParameterType::STRING;
        }

        $where = implode(' and ', $conditions);

        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE $where ORDER BY updated_at DESC, id",
            $params,
            $types
        );

        return $this->makeSessions($rows);
    }

    public function findByCredentialId(string $credentialId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE credential_id = :credential_id ORDER BY updated_at DESC, id",
            ['credential_id' => $credentialId]
        );

        return $this->makeSessions($rows);
    }

    public function findAktivnieSUnitom(int $limit): array
    {
        $table = self::TABLE;
        $unitsTable = self::UNITS_TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.* FROM $table s
             WHERE s.archived_at is null
               AND (s.unit_id is not null
                    OR exists (select 1 from $unitsTable u where u.session_id = s.id))
             ORDER BY s.updated_at ASC, s.id
             LIMIT :limit",
            ['limit' => max(1, $limit)],
            ['limit' => ParameterType::INTEGER]
        );

        return $this->makeSessions($rows);
    }

    /**
     * Workspace-сессии для фоновой сверки операций.
     *
     * Возвращаются все неархивные сессии, а также архивные, у которых workspace
     * в транзитном статусе. Без второго условия удаление архива
     * (`ARCHIVED → DELETING`) и восстановление из архива (`ARCHIVED → STARTING`)
     * никто бы не досверял: строка навсегда оставалась бы в `DELETING`/
     * `STARTING`, а интерфейс бесконечно крутил спиннер.
     *
     * Неархивные завершённые операции отбрасываются фильтром `archived_at`,
     * поэтому транзитные архивные сессии не вытесняют их из батча.
     */
    public function findAktivnieWorkspace(int $limit): array
    {
        $table = self::TABLE;
        $workspaceTable = self::WORKSPACE_TABLE;
        $tranzit = array_map(
            static fn (StatusWorkspace $status): string => $status->value,
            StatusWorkspace::tranzitnie(),
        );

        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.* FROM $table s
             INNER JOIN $workspaceTable w ON w.session_id = s.id
             WHERE s.tip = :tip
               AND (s.archived_at is null OR w.status = any(:tranzit))
             ORDER BY s.updated_at ASC, s.id
             LIMIT :limit",
            [
                'tip' => TipSessiiOpenCode::WORKSPACE->value,
                'tranzit' => '{' . implode(',', $tranzit) . '}',
                'limit' => max(1, $limit),
            ],
            [
                'tip' => ParameterType::STRING,
                'tranzit' => ParameterType::STRING,
                'limit' => ParameterType::INTEGER,
            ]
        );

        return $this->makeSessions($rows);
    }

    public function perevestiStatusWorkspace(
        string $sessionId,
        array $from,
        StatusWorkspace $to,
    ): bool {
        if ($from === []) {
            return false;
        }

        $fromValues = array_map(
            static fn (StatusWorkspace $status): string => $status->value,
            $from
        );

        $workspaceTable = self::WORKSPACE_TABLE;

        $updated = $this->connection->executeStatement(
            "update $workspaceTable set status = :to, updated_at = :now "
            . 'where session_id = :id and status = any(:from)',
            [
                'to' => $to->value,
                'now' => time(),
                'id' => $sessionId,
                'from' => '{' . implode(',', $fromValues) . '}',
            ],
            [
                'to' => ParameterType::STRING,
                'now' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
                'from' => ParameterType::STRING,
            ]
        );

        if ($updated > 0) {
            $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitSessiyu($sessionId));
        }

        return $updated > 0;
    }

    public function saveWorkspaceUpdate(
        string $sessionId,
        string $workflowId,
        WorkspacePendingUpdate $update,
    ): void {
        $table = self::WORKSPACE_UPDATE_TABLE;

        $this->connection->executeStatement(
            "insert into $table (session_id, workflow_id, payload, created_at)
             values (:session_id, :workflow_id, :payload, :created_at)
             on conflict (session_id) do update set
                workflow_id = excluded.workflow_id,
                payload = excluded.payload,
                created_at = excluded.created_at",
            [
                'session_id' => $sessionId,
                'workflow_id' => $workflowId,
                'payload' => json_encode($update->toArray(), JSON_THROW_ON_ERROR),
                'created_at' => time(),
            ],
            [
                'session_id' => ParameterType::STRING,
                'workflow_id' => ParameterType::STRING,
                'payload' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ]
        );

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitPendingUpdate($sessionId));
    }

    /**
     * @return array{workflowId: string, update: WorkspacePendingUpdate}|null
     */
    public function findWorkspaceUpdate(string $sessionId): ?array
    {
        $table = self::WORKSPACE_UPDATE_TABLE;

        $row = $this->connection->fetchAssociative(
            "select workflow_id, payload from $table where session_id = :session_id",
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING]
        );

        if ($row === false) {
            return null;
        }

        $payload = json_decode((string) $row['payload'], true);
        if (!is_array($payload)) {
            return null;
        }

        return [
            'workflowId' => (string) $row['workflow_id'],
            'update' => WorkspacePendingUpdate::fromArray($payload),
        ];
    }

    public function deleteWorkspaceUpdate(string $sessionId): void
    {
        $this->connection->delete(
            self::WORKSPACE_UPDATE_TABLE,
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING]
        );

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->udalitPendingUpdate($sessionId));
    }

    public function deleteWorkspace(string $sessionId): void
    {
        $this->connection->transactional(function () use ($sessionId): void {
            foreach ([
                self::WORKSPACE_UPDATE_TABLE,
                self::WORKSPACE_UNIT_TABLE,
                self::WORKSPACE_PROJECT_TABLE,
                self::WORKSPACE_TABLE,
                self::TURN_TABLE,
                self::NOTIFICATION_TABLE,
            ] as $table) {
                $this->connection->executeStatement(
                    "delete from $table where session_id = :id",
                    ['id' => $sessionId],
                    ['id' => ParameterType::STRING]
                );
            }

            $this->connection->executeStatement(
                'delete from ' . self::TABLE . ' where id = :id',
                ['id' => $sessionId],
                ['id' => ParameterType::STRING]
            );
        });

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->udalitWorkspace($sessionId));
    }

    /**
     * Best-effort зеркало: ошибка не должна ломать легаси-операцию.
     */
    private function zerkalit(callable $deistvie): void
    {
        if ($this->mirror === null || !$this->mirror->isEnabled()) {
            return;
        }

        try {
            $deistvie($this->mirror);
        } catch (\Throwable $exception) {
            $this->logger?->error('opencode.workspace.mirror_failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(SessiyaOpenCode $session): array
    {
        return [
            'id' => $session->getId(),
            'account_id' => $session->getAccountId(),
            'credential_id' => $session->getCredentialId(),
            'title' => $session->getTitle(),
            'tip' => $session->getTip()->value,
            'external_session_id' => $session->getExternalSessionId(),
            'unit_id' => $session->getUnitId(),
            'unit_project_id' => $session->getUnitProjectId(),
            'archived_at' => $session->getArchivedAt(),
            'archive_reason' => $session->getArchiveReason(),
            'model' => $session->getModel(),
            'created_at' => $session->getCreatedAt(),
            'updated_at' => $session->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionTypes(): array
    {
        return [
            'id' => ParameterType::STRING,
            'account_id' => ParameterType::STRING,
            'credential_id' => ParameterType::STRING,
            'title' => ParameterType::STRING,
            'tip' => ParameterType::STRING,
            'external_session_id' => ParameterType::STRING,
            'unit_id' => ParameterType::STRING,
            'unit_project_id' => ParameterType::STRING,
            'archived_at' => ParameterType::INTEGER,
            'archive_reason' => ParameterType::STRING,
            'model' => ParameterType::STRING,
            'created_at' => ParameterType::INTEGER,
            'updated_at' => ParameterType::INTEGER,
        ];
    }

    /**
     * Полная замена списка юнитов сессии: порядок юнитов задаёт position_index,
     * нулевая позиция — рабочий каталог (cwd) хода.
     */
    private function synchronizirovatPrivyazki(SessiyaOpenCode $session): void
    {
        $this->connection->delete(
            self::UNITS_TABLE,
            ['session_id' => $session->getId()],
            ['session_id' => ParameterType::STRING]
        );

        foreach ($session->getUnits() as $position => $privyazka) {
            $this->connection->insert(
                self::UNITS_TABLE,
                [
                    'session_id' => $session->getId(),
                    'unit_id' => $privyazka->unitId,
                    'unit_project_id' => $privyazka->unitProjectId,
                    'position_index' => $position,
                    'created_at' => $session->getCreatedAt(),
                ],
                [
                    'session_id' => ParameterType::STRING,
                    'unit_id' => ParameterType::STRING,
                    'unit_project_id' => ParameterType::STRING,
                    'position_index' => ParameterType::INTEGER,
                    'created_at' => ParameterType::INTEGER,
                ]
            );
        }
    }

    /**
     * Сохраняет workspace и его проекты в рамках уже открытой транзакции.
     */
    private function sohranitWorkspace(SessiyaOpenCode $session): void
    {
        $workspace = $session->getWorkspace();

        if ($workspace === null) {
            return;
        }

        $this->connection->executeStatement(
            'insert into ' . self::WORKSPACE_TABLE . ' ('
            . 'session_id, branch, status, read_only, initial_prompt, initial_prompt_started_at, '
            . 'error_key, operation, operation_workflow_id, operation_attempts, next_attempt_at, '
            . 'created_at, updated_at'
            . ') values ('
            . ':session_id, :branch, :status, :read_only, :initial_prompt, :initial_prompt_started_at, '
            . ':error_key, :operation, :operation_workflow_id, :operation_attempts, :next_attempt_at, '
            . ':created_at, :updated_at'
            . ') on conflict (session_id) do update set '
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
            . 'updated_at = excluded.updated_at',
            [
                'session_id' => $session->getId(),
                'branch' => $workspace->getBranch(),
                'status' => $workspace->getStatus()->value,
                'read_only' => $workspace->isReadOnly(),
                'initial_prompt' => $workspace->getInitialPrompt(),
                'initial_prompt_started_at' => $workspace->getInitialPromptStartedAt(),
                'error_key' => $workspace->getErrorKey(),
                'operation' => $workspace->getOperation(),
                'operation_workflow_id' => $workspace->getOperationWorkflowId(),
                'operation_attempts' => $workspace->getOperationAttempts(),
                'next_attempt_at' => $workspace->getNextAttemptAt(),
                'created_at' => $workspace->getCreatedAt(),
                'updated_at' => $workspace->getUpdatedAt(),
            ],
            [
                'session_id' => ParameterType::STRING,
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
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ]
        );

        $this->connection->delete(
            self::WORKSPACE_PROJECT_TABLE,
            ['session_id' => $session->getId()],
            ['session_id' => ParameterType::STRING]
        );

        $this->connection->delete(
            self::WORKSPACE_UNIT_TABLE,
            ['workspace_id' => $session->getId()],
            ['workspace_id' => ParameterType::STRING]
        );

        foreach ($workspace->getUnits() as $unit) {
            $this->connection->insert(
                self::WORKSPACE_UNIT_TABLE,
                [
                    'workspace_id' => $session->getId(),
                    'unit_id' => $unit->unitId,
                    'project_id' => $unit->projectId,
                    'unit_name' => $unit->unitName,
                    'branch' => $unit->branch,
                    'created_at' => $session->getUpdatedAt(),
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

        foreach ($workspace->getProjects() as $project) {
            $this->connection->insert(
                self::WORKSPACE_PROJECT_TABLE,
                [
                    'session_id' => $session->getId(),
                    'project_id' => $project->projectId,
                    'project_name' => $project->projectName,
                    'project_code' => $project->projectCode,
                    'main_branch' => $project->mainBranch,
                    'branch' => $project->branch,
                    'can_push' => $project->canPush,
                    'position_index' => $project->positionIndex,
                    'created_at' => $session->getCreatedAt(),
                ],
                [
                    'session_id' => ParameterType::STRING,
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
     * @param list<string> $sessionIds
     *
     * @return array<string, list<PrivyazkaKUnitu>>
     */
    private function zagruzitPrivyazki(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $unitsTable = self::UNITS_TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT session_id, unit_id, unit_project_id FROM $unitsTable
             WHERE session_id IN (:sessionIds)
             ORDER BY session_id ASC, position_index ASC, unit_id ASC",
            ['sessionIds' => $sessionIds],
            ['sessionIds' => ArrayParameterType::STRING]
        );

        $result = [];

        foreach ($rows as $row) {
            $sessionId = (string) $row['session_id'];
            $result[$sessionId] ??= [];
            $result[$sessionId][] = PrivyazkaKUnitu::izZnacheniy(
                is_string($row['unit_id']) ? $row['unit_id'] : null,
                is_string($row['unit_project_id']) ? $row['unit_project_id'] : null,
            );
        }

        return $result;
    }

    /**
     * Batch-чтение workspace и проектов: два запроса на всю страницу сессий.
     *
     * @param list<string> $sessionIds
     *
     * @return array<string, Workspace>
     */
    private function zagruzitWorkspaces(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $workspaceTable = self::WORKSPACE_TABLE;
        $projectTable = self::WORKSPACE_PROJECT_TABLE;

        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $workspaceTable WHERE session_id IN (:sessionIds)",
            ['sessionIds' => $sessionIds],
            ['sessionIds' => ArrayParameterType::STRING]
        );

        if ($rows === []) {
            return [];
        }

        $projectRows = $this->connection->fetchAllAssociative(
            "SELECT session_id, project_id, project_name, project_code, main_branch, branch, can_push, position_index FROM $projectTable
             WHERE session_id IN (:sessionIds)
             ORDER BY session_id ASC, position_index ASC",
            ['sessionIds' => $sessionIds],
            ['sessionIds' => ArrayParameterType::STRING]
        );

        $unitTable = self::WORKSPACE_UNIT_TABLE;

        $unitRows = $this->connection->fetchAllAssociative(
            "SELECT workspace_id, unit_id, project_id, unit_name, branch FROM $unitTable
             WHERE workspace_id IN (:sessionIds)
             ORDER BY workspace_id ASC, created_at ASC, unit_id ASC",
            ['sessionIds' => $sessionIds],
            ['sessionIds' => ArrayParameterType::STRING]
        );

        /** @var array<string, list<ProektWorkspace>> $projects */
        $projects = [];

        /** @var array<string, list<WorkspaceUnit>> $units */
        $units = [];

        foreach ($unitRows as $unitRow) {
            $sessionId = (string) $unitRow['workspace_id'];
            $units[$sessionId] ??= [];
            $units[$sessionId][] = new WorkspaceUnit(
                (string) $unitRow['unit_id'],
                (string) $unitRow['project_id'],
                (string) $unitRow['unit_name'],
                is_string($unitRow['branch'] ?? null) ? $unitRow['branch'] : '',
            );
        }

        foreach ($projectRows as $projectRow) {
            $sessionId = (string) $projectRow['session_id'];
            $projects[$sessionId] ??= [];
            $projects[$sessionId][] = new ProektWorkspace(
                (string) $projectRow['project_id'],
                (string) $projectRow['project_name'],
                (string) $projectRow['project_code'],
                self::toBool($projectRow['can_push'] ?? false),
                (int) ($projectRow['position_index'] ?? 0),
                is_string($projectRow['main_branch'] ?? null) ? $projectRow['main_branch'] : '',
                is_string($projectRow['branch'] ?? null) ? $projectRow['branch'] : '',
            );
        }

        $result = [];

        foreach ($rows as $row) {
            $sessionId = (string) $row['session_id'];

            $result[$sessionId] = Workspace::restore(
                (string) $row['branch'],
                (string) $row['initial_prompt'],
                $projects[$sessionId] ?? [],
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
                $units[$sessionId] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<SessiyaOpenCode>
     */
    private function makeSessions(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $workspaceIds = [];
        $unitIds = [];

        foreach ($rows as $row) {
            $id = (string) $row['id'];

            if ($this->tipIzStroki($row) === TipSessiiOpenCode::WORKSPACE) {
                $workspaceIds[] = $id;
            } else {
                $unitIds[] = $id;
            }
        }

        $workspaces = $this->zagruzitWorkspaces($workspaceIds);
        $privyazki = $this->zagruzitPrivyazki($unitIds);

        $result = [];

        foreach ($rows as $row) {
            $id = (string) $row['id'];

            if ($this->tipIzStroki($row) === TipSessiiOpenCode::WORKSPACE && isset($workspaces[$id])) {
                $result[] = $this->makeWorkspaceSession($row, $workspaces[$id]);

                continue;
            }

            $sessionPrivyazki = $privyazki[$id] ?? [];
            $result[] = $this->makeSession(
                $row,
                $sessionPrivyazki !== [] ? $sessionPrivyazki : $this->legacyPrivyazka($row)
            );
        }

        return $result;
    }

    private static function quoteEscapeChar(): string
    {
        return "'" . self::LIKE_ESCAPE_CHAR . "'";
    }

    /**
     * Экранирует спецсимволы LIKE, чтобы пользовательская строка искалась буквально.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE_CHAR, '%', '_'],
            [self::LIKE_ESCAPE_CHAR . self::LIKE_ESCAPE_CHAR, self::LIKE_ESCAPE_CHAR . '%', self::LIKE_ESCAPE_CHAR . '_'],
            $value
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function tipIzStroki(array $row): TipSessiiOpenCode
    {
        $tip = $row['tip'] ?? TipSessiiOpenCode::UNIT->value;

        if (!is_string($tip)) {
            return TipSessiiOpenCode::UNIT;
        }

        return TipSessiiOpenCode::tryFrom($tip) ?? TipSessiiOpenCode::UNIT;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<PrivyazkaKUnitu> $privyazki
     */
    private function makeSession(array $row, array $privyazki): SessiyaOpenCode
    {
        return SessiyaOpenCode::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['credential_id'],
            (string) $row['title'],
            self::nullableString($row['external_session_id'] ?? null),
            $privyazki,
            self::nullableInt($row['archived_at'] ?? null),
            self::nullableString($row['archive_reason'] ?? null),
            (int) $row['created_at'],
            (int) $row['updated_at'],
            self::nullableString($row['model'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeWorkspaceSession(array $row, Workspace $workspace): SessiyaOpenCode
    {
        return SessiyaOpenCode::restoreWorkspace(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['credential_id'],
            (string) $row['title'],
            self::nullableString($row['external_session_id'] ?? null),
            self::nullableInt($row['archived_at'] ?? null),
            self::nullableString($row['archive_reason'] ?? null),
            $workspace,
            (int) $row['created_at'],
            (int) $row['updated_at'],
            self::nullableString($row['model'] ?? null),
        );
    }

    /**
     * Совместимость с сессиями, привязанными до появления таблицы `opencode_session_unit`.
     *
     * @param array<string, mixed> $row
     *
     * @return list<PrivyazkaKUnitu>
     */
    private function legacyPrivyazka(array $row): array
    {
        $unitId = $row['unit_id'] ?? null;
        $unitProjectId = $row['unit_project_id'] ?? null;

        if (!is_string($unitId) || $unitId === '' || !is_string($unitProjectId) || $unitProjectId === '') {
            return [];
        }

        return [PrivyazkaKUnitu::izZnacheniy($unitId, $unitProjectId)];
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
