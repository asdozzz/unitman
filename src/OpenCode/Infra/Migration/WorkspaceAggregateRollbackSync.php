<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Обратный перенос нового workspace-агрегата в легаси-таблицы.
 *
 * Нужен для отката флага `OPENCODE_WORKSPACE_WRITE`: пока запись шла только в
 * новый агрегат, легаси оставался «холодным». Команда догоняет его, не удаляя
 * и не перезаписывая более свежие легаси-данные.
 *
 * Ограничения:
 * - пишет только в легаси-таблицы; нового агрегата не касается;
 * - не создаёт unit-сессии, `tip` всегда `workspace`;
 * - `on conflict` по естественному ключу делает повторный запуск безопасным;
 * - строки сессий старше `cutover_ts` не переносятся.
 */
final class WorkspaceAggregateRollbackSync
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, int>
     */
    public function run(int $cutoverTs = 0): array
    {
        return $this->connection->transactional(function () use ($cutoverTs): array {
            return [
                'sessions' => $this->perenestiSessii($cutoverTs),
                'workspaces' => $this->perenestiWorkspace($cutoverTs),
                'projects' => $this->perenestiProekty($cutoverTs),
                'pending_updates' => $this->perenestiPending($cutoverTs),
                'participants' => $this->perenestiUchastnikov($cutoverTs),
                'turns' => $this->perenestiHody($cutoverTs),
                'notifications' => $this->perenestiUvedomleniya($cutoverTs),
            ];
        });
    }

    private function perenestiSessii(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_session ('
            . 'id, account_id, credential_id, title, external_session_id, unit_id, unit_project_id, '
            . 'archived_at, archive_reason, tip, model, created_at, updated_at'
            . ') select '
            . 'ws.id, ws.account_id, ws.credential_id, ws.name, ws.external_session_id, null, null, '
            . 'ws.archived_at, ws.archive_reason, :tip, ws.model, ws.created_at, ws.updated_at '
            . 'from opencode_workspace_session ws '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (id) do update set '
            . 'account_id = excluded.account_id, '
            . 'credential_id = excluded.credential_id, '
            . 'title = excluded.title, '
            . 'external_session_id = excluded.external_session_id, '
            . 'archived_at = excluded.archived_at, '
            . 'archive_reason = excluded.archive_reason, '
            . 'tip = excluded.tip, '
            . 'model = excluded.model, '
            . 'updated_at = excluded.updated_at '
            . 'where opencode_session.updated_at <= excluded.updated_at',
            ['tip' => 'workspace', 'cutover_ts' => $cutoverTs],
            ['tip' => ParameterType::STRING, 'cutover_ts' => ParameterType::INTEGER]
        );
    }

    private function perenestiWorkspace(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace ('
            . 'session_id, branch, status, read_only, initial_prompt, initial_prompt_started_at, '
            . 'error_key, operation, operation_workflow_id, operation_attempts, next_attempt_at, '
            . 'created_at, updated_at'
            . ') select '
            . 'ws.id, ws.branch, ws.status, ws.read_only, ws.initial_prompt, ws.initial_prompt_started_at, '
            . 'ws.error_key, ws.operation, ws.operation_workflow_id, ws.operation_attempts, ws.next_attempt_at, '
            . 'ws.created_at, ws.updated_at '
            . 'from opencode_workspace_session ws '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (session_id) do update set '
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
            . 'updated_at = excluded.updated_at '
            . 'where opencode_workspace.updated_at <= excluded.updated_at',
            ['cutover_ts' => $cutoverTs],
            ['cutover_ts' => ParameterType::INTEGER]
        );
    }

    private function perenestiProekty(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace_project ('
            . 'session_id, project_id, project_name, project_code, main_branch, branch, can_push, '
            . 'position_index, created_at'
            . ') select '
            . 'p.workspace_id, p.project_id, p.project_name, p.project_code, p.main_branch, p.branch, '
            . 'p.can_push, p.position_index, p.created_at '
            . 'from opencode_workspace_session_project p '
            . 'join opencode_workspace_session ws on ws.id = p.workspace_id '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (session_id, project_id) do update set '
            . 'project_name = excluded.project_name, '
            . 'project_code = excluded.project_code, '
            . 'main_branch = excluded.main_branch, '
            . 'branch = excluded.branch, '
            . 'can_push = excluded.can_push, '
            . 'position_index = excluded.position_index',
            ['cutover_ts' => $cutoverTs],
            ['cutover_ts' => ParameterType::INTEGER]
        );
    }

    private function perenestiPending(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace_update (session_id, workflow_id, payload, created_at) '
            . 'select u.workspace_id, u.workflow_id, u.payload, u.created_at '
            . 'from opencode_workspace_pending_update u '
            . 'join opencode_workspace_session ws on ws.id = u.workspace_id '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (session_id) do update set '
            . 'workflow_id = excluded.workflow_id, payload = excluded.payload, created_at = excluded.created_at',
            ['cutover_ts' => $cutoverTs],
            ['cutover_ts' => ParameterType::INTEGER]
        );
    }

    private function perenestiUchastnikov(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_session_participant ('
            . 'session_id, account_id, role, default_credential_id, default_model, invited_by, '
            . 'created_at, updated_at'
            . ') select '
            . 'p.workspace_id, p.account_id, p.role, p.default_credential_id, p.default_model, '
            . 'p.invited_by, p.created_at, p.updated_at '
            . 'from opencode_workspace_participant p '
            . 'join opencode_workspace_session ws on ws.id = p.workspace_id '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (session_id, account_id) do update set '
            . 'role = excluded.role, '
            . 'default_credential_id = excluded.default_credential_id, '
            . 'default_model = excluded.default_model, '
            . 'invited_by = excluded.invited_by, '
            . 'updated_at = excluded.updated_at '
            . 'where opencode_session_participant.updated_at <= excluded.updated_at',
            ['cutover_ts' => $cutoverTs],
            ['cutover_ts' => ParameterType::INTEGER]
        );
    }

    private function perenestiHody(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_turn ('
            . 'id, session_id, seq, workflow_id, status, request_text, answers_json, result_json, '
            . 'error_key, model, author_account_id, credential_id, author_display_name, '
            . 'created_at, finished_at'
            . ') select '
            . 't.id, t.workspace_id, t.seq, t.workflow_id, t.status, t.request_text, t.answers_json, '
            . 't.result_json, t.error_key, t.model, t.author_account_id, t.credential_id, '
            . 't.author_display_name, t.created_at, t.finished_at '
            . 'from opencode_workspace_turn t '
            . 'join opencode_workspace_session ws on ws.id = t.workspace_id '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (id) do update set '
            . 'status = excluded.status, '
            . 'answers_json = excluded.answers_json, '
            . 'result_json = excluded.result_json, '
            . 'error_key = excluded.error_key, '
            . 'model = excluded.model, '
            . 'author_account_id = excluded.author_account_id, '
            . 'credential_id = excluded.credential_id, '
            . 'author_display_name = excluded.author_display_name, '
            . 'finished_at = excluded.finished_at',
            ['cutover_ts' => $cutoverTs],
            ['cutover_ts' => ParameterType::INTEGER]
        );
    }

    private function perenestiUvedomleniya(int $cutoverTs): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_notification ('
            . 'id, account_id, session_id, session_type, turn_id, session_title, status, kind, '
            . 'created_at, read_at'
            . ') select '
            . 'n.id, n.account_id, n.workspace_id, :workspace_type, n.turn_id, n.workspace_name, '
            . 'n.status, n.kind, n.created_at, n.read_at '
            . 'from opencode_workspace_notification n '
            . 'join opencode_workspace_session ws on ws.id = n.workspace_id '
            . 'where ws.updated_at > :cutover_ts '
            . 'on conflict (turn_id, account_id) do update set '
            . 'status = excluded.status, '
            . 'kind = excluded.kind, '
            . 'read_at = coalesce(excluded.read_at, opencode_notification.read_at)',
            ['workspace_type' => 'workspace', 'cutover_ts' => $cutoverTs],
            ['workspace_type' => ParameterType::STRING, 'cutover_ts' => ParameterType::INTEGER]
        );
    }
}
