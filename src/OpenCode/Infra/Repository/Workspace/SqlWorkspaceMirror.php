<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository\Workspace;

use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Best-effort зеркало легаси-агрегата в новый workspace-агрегат.
 *
 * Источник истины — легаси-таблицы. Зеркало только читает их и пишет в
 * `opencode_workspace_*`; обратной записи нет. Каждая операция обёрнута в
 * `try/catch`: ошибка зеркала логируется и не пробрасывается в легаси-путь.
 * Расхождение после ошибки ловит `app:opencode:workspace-migration:check`.
 */
final class SqlWorkspaceMirror implements WorkspaceMirror
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function zerkalitSessiyu(string $sessionId): void
    {
        $this->bezopasno('session', $sessionId, function () use ($sessionId): void {
            if (!$this->estWorkspace($sessionId)) {
                return;
            }

            $this->connection->transactional(function () use ($sessionId): void {
                $this->connection->executeStatement(
                    'insert into opencode_workspace_session ('
                    . 'id, account_id, credential_id, name, branch, status, read_only, initial_prompt, '
                    . 'initial_prompt_started_at, error_key, operation, operation_workflow_id, '
                    . 'operation_attempts, next_attempt_at, external_session_id, archived_at, '
                    . 'archive_reason, model, created_at, updated_at'
                    . ') select '
                    . 's.id, s.account_id, s.credential_id, s.title, w.branch, w.status, w.read_only, '
                    . 'w.initial_prompt, w.initial_prompt_started_at, w.error_key, w.operation, '
                    . 'w.operation_workflow_id, w.operation_attempts, w.next_attempt_at, '
                    . 's.external_session_id, s.archived_at, s.archive_reason, s.model, '
                    . 's.created_at, s.updated_at '
                    . 'from opencode_session s '
                    . 'join opencode_workspace w on w.session_id = s.id '
                    . 'where s.id = :id and s.tip = :tip '
                    . 'on conflict (id) do update set '
                    . 'account_id = excluded.account_id, '
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
                    . 'updated_at = excluded.updated_at '
                    . 'where opencode_workspace_session.created_at = excluded.created_at '
                    . 'and opencode_workspace_session.updated_at <= excluded.updated_at',
                    ['id' => $sessionId, 'tip' => 'workspace']
                );

                // Полная замена списка проектов, как в легаси-репозитории.
                $this->connection->executeStatement(
                    'delete from opencode_workspace_session_project p '
                    . 'where p.workspace_id = :id '
                    . 'and not exists ('
                    . 'select 1 from opencode_workspace_project lp '
                    . 'where lp.session_id = :id and lp.project_id = p.project_id)',
                    ['id' => $sessionId]
                );

                $this->connection->executeStatement(
                    'insert into opencode_workspace_session_project ('
                    . 'workspace_id, project_id, project_name, project_code, main_branch, '
                    . 'branch, can_push, position_index, created_at'
                    . ') select '
                    . 'p.session_id, p.project_id, p.project_name, p.project_code, p.main_branch, '
                    . 'p.branch, p.can_push, p.position_index, p.created_at '
                    . 'from opencode_workspace_project p '
                    . 'where p.session_id = :id '
                    . 'on conflict (workspace_id, project_id) do update set '
                    . 'project_name = excluded.project_name, '
                    . 'project_code = excluded.project_code, '
                    . 'main_branch = excluded.main_branch, '
                    . 'branch = excluded.branch, '
                    . 'can_push = excluded.can_push, '
                    . 'position_index = excluded.position_index',
                    ['id' => $sessionId]
                );

                $this->zerkalitPendingUpdateBezOshibok($sessionId);
            });
        });
    }

    public function zerkalitHod(string $turnId): void
    {
        $this->bezopasno('turn', $turnId, function () use ($turnId): void {
            $row = $this->connection->fetchAssociative(
                'select t.*, s.tip, s.account_id as session_account_id, '
                . 's.credential_id as session_credential_id from opencode_turn t '
                . 'join opencode_session s on s.id = t.session_id '
                . 'where t.id = :id',
                ['id' => $turnId]
            );

            if ($row === false || $row === [] || (string) ($row['tip'] ?? '') !== 'workspace') {
                return;
            }

            $workspaceId = (string) $row['session_id'];
            $this->trebovatSessiyuVAgregate($workspaceId);

            $this->connection->executeStatement(
                'insert into opencode_workspace_turn ('
                . 'id, workspace_id, seq, workflow_id, status, request_text, answers_json, '
                . 'result_json, error_key, model, agent_mode_id, agent_mode_name, '
                . 'author_account_id, credential_id, '
                . 'author_display_name, created_at, finished_at'
                . ') values ('
                . ':id, :workspace_id, :seq, :workflow_id, :status, :request_text, :answers_json, '
                . ':result_json, :error_key, :model, :agent_mode_id, :agent_mode_name, '
                . ':author_account_id, :credential_id, '
                . ':author_display_name, :created_at, :finished_at'
                . ') on conflict (id) do update set '
                . 'status = excluded.status, '
                . 'answers_json = excluded.answers_json, '
                . 'result_json = excluded.result_json, '
                . 'error_key = excluded.error_key, '
                . 'model = excluded.model, '
                . 'agent_mode_id = excluded.agent_mode_id, '
                . 'agent_mode_name = excluded.agent_mode_name, '
                . 'author_account_id = excluded.author_account_id, '
                . 'credential_id = excluded.credential_id, '
                . 'author_display_name = excluded.author_display_name, '
                . 'finished_at = excluded.finished_at',
                [
                    'id' => (string) $row['id'],
                    'workspace_id' => $workspaceId,
                    'seq' => (int) $row['seq'],
                    'workflow_id' => (string) $row['workflow_id'],
                    'status' => (string) $row['status'],
                    'request_text' => (string) $row['request_text'],
                    'answers_json' => (string) ($row['answers_json'] ?? '[]'),
                    'result_json' => $row['result_json'] !== null ? (string) $row['result_json'] : null,
                    'error_key' => $row['error_key'] !== null ? (string) $row['error_key'] : null,
                    'model' => $row['model'] !== null ? (string) $row['model'] : null,
                    'agent_mode_id' => $row['agent_mode_id'] !== null ? (string) $row['agent_mode_id'] : null,
                    'agent_mode_name' => (string) ($row['agent_mode_name'] ?? ''),
                    'author_account_id' => (string) (($row['author_account_id'] ?? '') !== ''
                        ? $row['author_account_id']
                        : ($row['session_account_id'] ?? '')),
                    'credential_id' => (string) (($row['credential_id'] ?? '') !== ''
                        ? $row['credential_id']
                        : ($row['session_credential_id'] ?? '')),
                    'author_display_name' => (string) ($row['author_display_name'] ?? ''),
                    'created_at' => (int) $row['created_at'],
                    'finished_at' => $row['finished_at'] !== null ? (int) $row['finished_at'] : null,
                ]
            );
        });
    }

    public function zerkalitUchastnika(string $sessionId, string $accountId): void
    {
        $this->bezopasno('participant', $sessionId, function () use ($sessionId, $accountId): void {
            if (!$this->estWorkspace($sessionId)) {
                return;
            }

            $this->trebovatSessiyuVAgregate($sessionId);

            $this->connection->executeStatement(
                'insert into opencode_workspace_participant ('
                . 'workspace_id, account_id, role, default_credential_id, default_model, '
                . 'invited_by, created_at, updated_at'
                . ') select '
                . 'p.session_id, p.account_id, p.role, p.default_credential_id, p.default_model, '
                . 'p.invited_by, p.created_at, p.updated_at '
                . 'from opencode_session_participant p '
                . 'where p.session_id = :workspace_id and p.account_id = :account_id '
                . 'on conflict (workspace_id, account_id) do update set '
                . 'role = excluded.role, '
                . 'default_credential_id = excluded.default_credential_id, '
                . 'default_model = excluded.default_model, '
                . 'invited_by = excluded.invited_by, '
                . 'updated_at = excluded.updated_at',
                ['workspace_id' => $sessionId, 'account_id' => $accountId]
            );
        });
    }

    public function udalitUchastnika(string $sessionId, string $accountId): void
    {
        $this->bezopasno('participant_delete', $sessionId, function () use ($sessionId, $accountId): void {
            $this->connection->delete(
                'opencode_workspace_participant',
                ['workspace_id' => $sessionId, 'account_id' => $accountId]
            );
        });
    }

    public function zerkalitUvedomlenie(string $notificationId): void
    {
        $this->bezopasno('notification', $notificationId, function () use ($notificationId): void {
            $row = $this->connection->fetchAssociative(
                'select n.*, s.tip from opencode_notification n '
                . 'join opencode_session s on s.id = n.session_id '
                . 'where n.id = :id',
                ['id' => $notificationId]
            );

            if ($row === false || $row === [] || (string) ($row['tip'] ?? '') !== 'workspace') {
                return;
            }

            $workspaceId = (string) $row['session_id'];
            $this->trebovatSessiyuVAgregate($workspaceId);

            $this->connection->executeStatement(
                'insert into opencode_workspace_notification ('
                . 'id, account_id, workspace_id, turn_id, workspace_name, status, kind, '
                . 'created_at, read_at'
                . ') values ('
                . ':id, :account_id, :workspace_id, :turn_id, :workspace_name, :status, :kind, '
                . ':created_at, :read_at'
                . ') on conflict (turn_id, account_id) do update set '
                . 'workspace_name = excluded.workspace_name, '
                . 'status = excluded.status, '
                . 'kind = excluded.kind, '
                . 'read_at = excluded.read_at',
                [
                    'id' => (string) $row['id'],
                    'account_id' => (string) $row['account_id'],
                    'workspace_id' => $workspaceId,
                    'turn_id' => (string) $row['turn_id'],
                    'workspace_name' => (string) ($row['session_title'] ?? ''),
                    'status' => (string) $row['status'],
                    'kind' => $row['kind'] !== null ? (string) $row['kind'] : null,
                    'created_at' => (int) $row['created_at'],
                    'read_at' => $row['read_at'] !== null ? (int) $row['read_at'] : null,
                ]
            );
        });
    }

    public function zerkalitProchitannost(array $notificationIds): void
    {
        if ($notificationIds === []) {
            return;
        }

        $this->bezopasno('notification_read', implode(',', $notificationIds), function () use ($notificationIds): void {
            $this->connection->executeStatement(
                'update opencode_workspace_notification t '
                . 'set read_at = n.read_at '
                . 'from opencode_notification n '
                . 'where t.id = n.id and t.id in (:ids)',
                ['ids' => $notificationIds],
                ['ids' => ArrayParameterType::STRING]
            );
        });
    }

    public function zerkalitPendingUpdate(string $sessionId): void
    {
        $this->bezopasno('pending', $sessionId, function () use ($sessionId): void {
            if (!$this->estWorkspace($sessionId)) {
                return;
            }

            $this->trebovatSessiyuVAgregate($sessionId);
            $this->zerkalitPendingUpdateBezOshibok($sessionId);
        });
    }

    public function udalitPendingUpdate(string $sessionId): void
    {
        $this->bezopasno('pending_delete', $sessionId, function () use ($sessionId): void {
            $this->connection->delete('opencode_workspace_pending_update', ['workspace_id' => $sessionId]);
        });
    }

    public function udalitWorkspace(string $sessionId): void
    {
        $this->bezopasno('workspace_delete', $sessionId, function () use ($sessionId): void {
            $this->connection->transactional(function () use ($sessionId): void {
                foreach ([
                    'opencode_workspace_notification',
                    'opencode_workspace_turn',
                    'opencode_workspace_pending_update',
                    'opencode_workspace_participant',
                    'opencode_workspace_session_project',
                ] as $table) {
                    $column = $table === 'opencode_workspace_notification'
                        || $table === 'opencode_workspace_turn'
                        || $table === 'opencode_workspace_pending_update'
                        || $table === 'opencode_workspace_participant'
                        ? 'workspace_id'
                        : 'workspace_id';

                    $this->connection->delete($table, [$column => $sessionId]);
                }

                $this->connection->delete('opencode_workspace_session', ['id' => $sessionId]);
            });
        });
    }

    private function zerkalitPendingUpdateBezOshibok(string $sessionId): void
    {
        $this->connection->executeStatement(
            'delete from opencode_workspace_pending_update pu '
            . 'where pu.workspace_id = :id '
            . 'and not exists (select 1 from opencode_workspace_update lu where lu.session_id = :id)',
            ['id' => $sessionId]
        );

        $this->connection->executeStatement(
            'insert into opencode_workspace_pending_update (workspace_id, workflow_id, payload, created_at) '
            . 'select u.session_id, u.workflow_id, u.payload, u.created_at '
            . 'from opencode_workspace_update u '
            . 'where u.session_id = :id '
            . 'on conflict (workspace_id) do update set '
            . 'workflow_id = excluded.workflow_id, '
            . 'payload = excluded.payload, '
            . 'created_at = excluded.created_at',
            ['id' => $sessionId]
        );
    }

    private function trebovatSessiyuVAgregate(string $sessionId): void
    {
        $exists = $this->connection->fetchOne(
            'select 1 from opencode_workspace_session where id = :id',
            ['id' => $sessionId]
        );

        if ($exists === false) {
            $this->zerkalitSessiyu($sessionId);
        }
    }

    private function estWorkspace(string $sessionId): bool
    {
        return $this->connection->fetchOne(
            'select tip from opencode_session where id = :id',
            ['id' => $sessionId]
        ) === 'workspace';
    }

    private function bezopasno(string $operaciya, string $subject, callable $deistvie): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $deistvie();
        } catch (\Throwable $exception) {
            $this->logger->error('opencode.workspace.mirror_failed', [
                'operation' => $operaciya,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
