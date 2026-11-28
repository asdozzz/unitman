<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Migration;

use Doctrine\DBAL\Connection;

/**
 * Гейт сверки легаси workspace-агрегата и нового агрегата.
 *
 * Команда read-only: сравнивает количество строк и ключевые поля. Любое
 * расхождение делает `ok = false`, и CLI-команда возвращает ненулевой код
 * выхода. `seq` проверяется только для workspace без дублей в легаси: для
 * workspace с дублями целевая нумерация детерминированно пересчитана.
 */
final class WorkspaceAggregateParityChecker
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{ok: bool, checks: array<string, array<string, int>>}
     */
    public function proverit(): array
    {
        $checks = [
            'sessions' => [
                'missing' => $this->chislo(
                    'select count(*) from opencode_session s '
                    . 'join opencode_workspace w on w.session_id = s.id '
                    . 'left join opencode_workspace_session t on t.id = s.id '
                    . 'where s.tip = :tip and t.id is null',
                    ['tip' => 'workspace']
                ),
                'extra' => $this->chislo(
                    'select count(*) from opencode_workspace_session t '
                    . 'where not exists ('
                    . 'select 1 from opencode_session s join opencode_workspace w on w.session_id = s.id '
                    . 'where s.tip = :tip and s.id = t.id)',
                    ['tip' => 'workspace']
                ),
                'mismatched' => $this->chislo(
                    'select count(*) from opencode_session s '
                    . 'join opencode_workspace w on w.session_id = s.id '
                    . 'join opencode_workspace_session t on t.id = s.id '
                    . 'where s.tip = :tip and ('
                    . 't.account_id is distinct from s.account_id '
                    . 'or t.credential_id is distinct from s.credential_id '
                    . 'or t.name is distinct from s.title '
                    . 'or t.branch is distinct from w.branch '
                    . 'or t.status is distinct from w.status '
                    . 'or t.read_only is distinct from w.read_only '
                    . 'or t.initial_prompt is distinct from w.initial_prompt '
                    . 'or t.initial_prompt_started_at is distinct from w.initial_prompt_started_at '
                    . 'or t.error_key is distinct from w.error_key '
                    . 'or t.operation is distinct from w.operation '
                    . 'or t.operation_workflow_id is distinct from w.operation_workflow_id '
                    . 'or t.operation_attempts is distinct from w.operation_attempts '
                    . 'or t.next_attempt_at is distinct from w.next_attempt_at '
                    . 'or t.external_session_id is distinct from s.external_session_id '
                    . 'or t.archived_at is distinct from s.archived_at '
                    . 'or t.archive_reason is distinct from s.archive_reason '
                    . 'or t.model is distinct from s.model '
                    . 'or t.created_at is distinct from s.created_at '
                    . 'or t.updated_at is distinct from s.updated_at)',
                    ['tip' => 'workspace']
                ),
            ],
            'projects' => [
                'missing' => $this->chislo(
                    'select count(*) from opencode_workspace_project p '
                    . 'join opencode_workspace_session ws on ws.id = p.session_id '
                    . 'left join opencode_workspace_session_project t '
                    . 'on t.workspace_id = p.session_id and t.project_id = p.project_id '
                    . 'where t.project_id is null'
                ),
                'extra' => $this->chislo(
                    'select count(*) from opencode_workspace_session_project t '
                    . 'where not exists ('
                    . 'select 1 from opencode_workspace_project p '
                    . 'where p.session_id = t.workspace_id and p.project_id = t.project_id)'
                ),
                'mismatched' => $this->chislo(
                    'select count(*) from opencode_workspace_project p '
                    . 'join opencode_workspace_session_project t '
                    . 'on t.workspace_id = p.session_id and t.project_id = p.project_id '
                    . 'where t.project_name is distinct from p.project_name '
                    . 'or t.project_code is distinct from p.project_code '
                    . 'or t.main_branch is distinct from p.main_branch '
                    . 'or t.branch is distinct from p.branch '
                    . 'or t.can_push is distinct from p.can_push '
                    . 'or t.position_index is distinct from p.position_index '
                    . 'or t.created_at is distinct from p.created_at'
                ),
            ],
            'turns' => [
                'missing' => $this->chislo(
                    'select count(*) from opencode_turn l '
                    . 'join opencode_workspace_session ws on ws.id = l.session_id '
                    . 'left join opencode_workspace_turn t on t.id = l.id '
                    . 'where t.id is null'
                ),
                'extra' => $this->chislo(
                    'select count(*) from opencode_workspace_turn t '
                    . 'where not exists (select 1 from opencode_turn l where l.id = t.id)'
                ),
                'mismatched' => $this->chislo(
                    'select count(*) from opencode_turn l '
                    . 'join opencode_workspace_turn t on t.id = l.id '
                    . 'join opencode_session s on s.id = l.session_id '
                    . 'where t.workspace_id is distinct from l.session_id '
                    . 'or t.workflow_id is distinct from l.workflow_id '
                    . 'or t.status is distinct from l.status '
                    . 'or t.request_text is distinct from l.request_text '
                    . 'or t.answers_json is distinct from l.answers_json '
                    . 'or t.result_json is distinct from l.result_json '
                    . 'or t.error_key is distinct from l.error_key '
                    . 'or t.model is distinct from l.model '
                    . "or t.author_account_id is distinct from coalesce(nullif(l.author_account_id, ''), s.account_id) "
                    . "or t.credential_id is distinct from coalesce(nullif(l.credential_id, ''), s.credential_id) "
                    . "or t.author_display_name is distinct from coalesce(l.author_display_name, '') "
                    . 'or t.created_at is distinct from l.created_at '
                    . 'or t.finished_at is distinct from l.finished_at'
                ),
                'seq_mismatched' => $this->chislo(
                    'select count(*) from opencode_turn l '
                    . 'join opencode_workspace_turn t on t.id = l.id '
                    . 'where t.seq is distinct from l.seq '
                    . 'and not exists ('
                    . 'select 1 from opencode_turn d '
                    . 'where d.session_id = l.session_id '
                    . 'group by d.session_id '
                    . 'having count(*) <> count(distinct d.seq))'
                ),
            ],
            'notifications' => [
                'missing' => $this->chislo(
                    'select count(*) from opencode_notification n '
                    . 'join opencode_session s on s.id = n.session_id '
                    . 'left join opencode_workspace_notification t '
                    . 'on t.turn_id = n.turn_id and t.account_id = n.account_id '
                    . 'where s.tip = :tip and t.id is null',
                    ['tip' => 'workspace']
                ),
                'extra' => $this->chislo(
                    'select count(*) from opencode_workspace_notification t '
                    . 'where not exists ('
                    . 'select 1 from opencode_notification n '
                    . 'where n.turn_id = t.turn_id and n.account_id = t.account_id)'
                ),
                'mismatched' => $this->chislo(
                    'select count(*) from opencode_notification n '
                    . 'join opencode_workspace_notification t '
                    . 'on t.turn_id = n.turn_id and t.account_id = n.account_id '
                    . 'where t.workspace_id is distinct from n.session_id '
                    . 'or t.workspace_name is distinct from n.session_title '
                    . 'or t.status is distinct from n.status '
                    . 'or t.kind is distinct from n.kind '
                    . 'or t.created_at is distinct from n.created_at '
                    . 'or t.read_at is distinct from n.read_at'
                ),
            ],
            'participants' => [
                'missing' => $this->chislo(
                    'select count(*) from opencode_session_participant p '
                    . 'join opencode_workspace_session ws on ws.id = p.session_id '
                    . 'left join opencode_workspace_participant t '
                    . 'on t.workspace_id = p.session_id and t.account_id = p.account_id '
                    . 'where t.account_id is null'
                ),
                'extra' => $this->chislo(
                    // Строка owner для владельца — гарантированная backfill-ом
                    // неявная строка: в легаси её может не быть, и это не
                    // расхождение.
                    'select count(*) from opencode_workspace_participant t '
                    . 'where not exists ('
                    . 'select 1 from opencode_session_participant p '
                    . 'where p.session_id = t.workspace_id and p.account_id = t.account_id) '
                    . 'and not (t.role = :owner and exists ('
                    . 'select 1 from opencode_workspace_session ws '
                    . 'where ws.id = t.workspace_id and ws.account_id = t.account_id))',
                    ['owner' => 'owner']
                ),
                'mismatched' => $this->chislo(
                    'select count(*) from opencode_session_participant p '
                    . 'join opencode_workspace_participant t '
                    . 'on t.workspace_id = p.session_id and t.account_id = p.account_id '
                    . 'where t.role is distinct from p.role '
                    . 'or t.default_credential_id is distinct from p.default_credential_id '
                    . 'or t.default_model is distinct from p.default_model '
                    . 'or t.invited_by is distinct from p.invited_by '
                    . 'or t.updated_at is distinct from p.updated_at'
                ),
                'owner_missing' => $this->chislo(
                    "select count(*) from opencode_workspace_session ws
                     where not exists (
                         select 1 from opencode_workspace_participant t
                         where t.workspace_id = ws.id
                           and t.account_id = ws.account_id
                           and t.role = 'owner')"
                ),
            ],
            'pending_updates' => [
                'missing' => $this->chislo(
                    'select count(*) from opencode_workspace_update u '
                    . 'join opencode_workspace_session ws on ws.id = u.session_id '
                    . 'left join opencode_workspace_pending_update t on t.workspace_id = u.session_id '
                    . 'where t.workspace_id is null'
                ),
                'extra' => $this->chislo(
                    'select count(*) from opencode_workspace_pending_update t '
                    . 'where not exists (select 1 from opencode_workspace_update u where u.session_id = t.workspace_id)'
                ),
                'mismatched' => $this->chislo(
                    'select count(*) from opencode_workspace_update u '
                    . 'join opencode_workspace_pending_update t on t.workspace_id = u.session_id '
                    . 'where t.workflow_id is distinct from u.workflow_id '
                    . 'or t.payload is distinct from u.payload '
                    . 'or t.created_at is distinct from u.created_at'
                ),
            ],
        ];

        $ok = true;

        foreach ($checks as $values) {
            foreach ($values as $count) {
                if ($count > 0) {
                    $ok = false;
                }
            }
        }

        return ['ok' => $ok, 'checks' => $checks];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function chislo(string $sql, array $params = []): int
    {
        return (int) $this->connection->fetchOne($sql, $params);
    }
}
