<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Идемпотентный backfill легаси workspace-агрегата в новый агрегат.
 *
 * Источник — только `opencode_session.tip = 'workspace'` и связанные таблицы.
 * Unit-сессии не затрагиваются. Идентификаторы сохраняются:
 * `opencode_workspace_session.id = opencode_session.id`.
 *
 * Повторный запуск не дублирует строки: все вставки идут через
 * `on conflict` по естественному ключу. Легаси-таблицы не изменяются; дубли
 * `(session_id, seq)` перенумеровываются только в целевой таблице, а
 * отображение пишется в `opencode_workspace_migration_journal`.
 */
final class WorkspaceAggregateBackfill
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $report = [
            'sessions' => $this->perenestiSessii(),
            'projects' => $this->perenestiProekty(),
            'pending_updates' => $this->perenestiPending(),
            'participants' => $this->perenestiUchastnikov(),
            'owners' => $this->gabarantirovatOwner(),
            'owner_role_fixes' => $this->ispravitRolOwner(),
        ];

        [$turns, $renumbered] = $this->perenestiHody();
        $report['turns'] = $turns;
        $report['turns_renumbered_workspaces'] = $renumbered;
        $report['notifications'] = $this->perenestiUvedomleniya();

        return $report;
    }

    private function perenestiSessii(): int
    {
        return (int) $this->connection->executeStatement(
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
            . 'where s.tip = :tip '
            . 'on conflict (id) do update set '
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
            . 'where opencode_workspace_session.updated_at <= excluded.updated_at',
            ['tip' => 'workspace']
        );
    }

    private function perenestiProekty(): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace_session_project ('
            . 'workspace_id, project_id, project_name, project_code, main_branch, '
            . 'branch, can_push, position_index, created_at'
            . ') select '
            . 'p.session_id, p.project_id, p.project_name, p.project_code, p.main_branch, '
            . 'p.branch, p.can_push, p.position_index, p.created_at '
            . 'from opencode_workspace_project p '
            . 'join opencode_workspace_session ws on ws.id = p.session_id '
            . 'on conflict (workspace_id, project_id) do update set '
            . 'project_name = excluded.project_name, '
            . 'project_code = excluded.project_code, '
            . 'main_branch = excluded.main_branch, '
            . 'branch = excluded.branch, '
            . 'can_push = excluded.can_push, '
            . 'position_index = excluded.position_index'
        );
    }

    private function perenestiPending(): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace_pending_update (workspace_id, workflow_id, payload, created_at) '
            . 'select u.session_id, u.workflow_id, u.payload, u.created_at '
            . 'from opencode_workspace_update u '
            . 'join opencode_workspace_session ws on ws.id = u.session_id '
            . 'on conflict (workspace_id) do update set '
            . 'workflow_id = excluded.workflow_id, '
            . 'payload = excluded.payload, '
            . 'created_at = excluded.created_at'
        );
    }

    private function perenestiUchastnikov(): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace_participant ('
            . 'workspace_id, account_id, role, default_credential_id, default_model, '
            . 'invited_by, created_at, updated_at'
            . ') select '
            . 'p.session_id, p.account_id, p.role, p.default_credential_id, p.default_model, '
            . 'p.invited_by, p.created_at, p.updated_at '
            . 'from opencode_session_participant p '
            . 'join opencode_workspace_session ws on ws.id = p.session_id '
            . 'on conflict (workspace_id, account_id) do update set '
            . 'role = excluded.role, '
            . 'default_credential_id = excluded.default_credential_id, '
            . 'default_model = excluded.default_model, '
            . 'invited_by = excluded.invited_by, '
            . 'updated_at = excluded.updated_at'
        );
    }

    private function gabarantirovatOwner(): int
    {
        return (int) $this->connection->executeStatement(
            "insert into opencode_workspace_participant (
                workspace_id, account_id, role, default_credential_id, default_model,
                invited_by, created_at, updated_at
             )
             select ws.id, ws.account_id, 'owner', ws.credential_id, ws.model, null,
                    ws.created_at, ws.updated_at
             from opencode_workspace_session ws
             on conflict (workspace_id, account_id) do nothing"
        );
    }

    private function ispravitRolOwner(): int
    {
        // Аномалия: владелец уже есть строкой, но роль не owner. Правим только
        // если другого owner у workspace нет, иначе падает частичный индекс.
        return (int) $this->connection->executeStatement(
            "update opencode_workspace_participant p
             set role = 'owner', updated_at = ws.updated_at
             from opencode_workspace_session ws
             where p.workspace_id = ws.id
               and p.account_id = ws.account_id
               and p.role <> 'owner'
               and not exists (
                   select 1 from opencode_workspace_participant o
                   where o.workspace_id = p.workspace_id
                     and o.role = 'owner'
                     and o.account_id <> p.account_id
               )"
        );
    }

    /**
     * @return array{0: int, 1: int} [вставлено/обновлено ходов, перенумеровано workspace]
     */
    private function perenestiHody(): array
    {
        $dupWorkspaces = $this->workspaceIdsSDublikatamiSeq();
        $dupSet = array_fill_keys($dupWorkspaces, true);

        $workspaceIds = $this->connection->fetchFirstColumn(
            'select ws.id from opencode_workspace_session ws order by ws.id'
        );

        $inserted = 0;
        $renumbered = 0;

        foreach (array_chunk(array_map('strval', $workspaceIds), 500) as $chunk) {
            $bezDubley = array_values(array_filter(
                $chunk,
                static fn (string $id): bool => !isset($dupSet[$id])
            ));

            if ($bezDubley !== []) {
                $inserted += (int) $this->connection->executeStatement(
                    'insert into opencode_workspace_turn ('
                    . 'id, workspace_id, seq, workflow_id, status, request_text, answers_json, '
                    . 'result_json, error_key, model, author_account_id, credential_id, '
                    . 'author_display_name, created_at, finished_at'
                    . ') select '
                    . 't.id, t.session_id, t.seq, t.workflow_id, t.status, t.request_text, '
                    . 't.answers_json, t.result_json, t.error_key, t.model, '
                    . "coalesce(nullif(t.author_account_id, ''), s.account_id), "
                    . "coalesce(nullif(t.credential_id, ''), s.credential_id), "
                    . "coalesce(t.author_display_name, ''), t.created_at, t.finished_at "
                    . 'from opencode_turn t '
                    . 'join opencode_session s on s.id = t.session_id '
                    . 'where t.session_id in (:ids) '
                    . 'on conflict (id) do update set '
                    . 'status = excluded.status, '
                    . 'request_text = excluded.request_text, '
                    . 'answers_json = excluded.answers_json, '
                    . 'result_json = excluded.result_json, '
                    . 'error_key = excluded.error_key, '
                    . 'model = excluded.model, '
                    . 'author_account_id = excluded.author_account_id, '
                    . 'credential_id = excluded.credential_id, '
                    . 'author_display_name = excluded.author_display_name, '
                    . 'created_at = excluded.created_at, '
                    . 'finished_at = excluded.finished_at',
                    ['ids' => $bezDubley],
                    ['ids' => ArrayParameterType::STRING]
                );
            }

            foreach ($chunk as $workspaceId) {
                if (isset($dupSet[$workspaceId])) {
                    $inserted += $this->perenumerovatHodyWorkspace($workspaceId);
                    ++$renumbered;
                }
            }
        }

        return [$inserted, $renumbered];
    }

    private function perenumerovatHodyWorkspace(string $workspaceId): int
    {
        return (int) $this->connection->transactional(function () use ($workspaceId): int {
            $turnRows = $this->connection->fetchAllAssociative(
                'select id, seq from opencode_turn where session_id = :id '
                . 'order by created_at asc, id asc',
                ['id' => $workspaceId]
            );

            if ($turnRows === []) {
                return 0;
            }

            $ids = array_map(static fn (array $row): string => (string) $row['id'], $turnRows);

            // Уводим уже перенесённые строки этого workspace за пределы
            // диапазона новых seq, чтобы уникальный индекс не сработал в
            // середине перенумерации. Затрагиваются только целевые строки.
            $maxTargetSeq = (int) $this->connection->fetchOne(
                'select coalesce(max(seq), 0) from opencode_workspace_turn where workspace_id = :id',
                ['id' => $workspaceId]
            );
            $shift = $maxTargetSeq + count($ids) + 1;

            $this->connection->executeStatement(
                'update opencode_workspace_turn set seq = seq + :shift '
                . 'where workspace_id = :workspace_id and id in (:ids)',
                ['shift' => $shift, 'workspace_id' => $workspaceId, 'ids' => $ids],
                [
                    'shift' => ParameterType::INTEGER,
                    'workspace_id' => ParameterType::STRING,
                    'ids' => ArrayParameterType::STRING,
                ]
            );

            // Сироты/строки, которых нет в легаси, не трогаем: новые seq
            // начинаются выше их максимума.
            $orphanMax = (int) $this->connection->fetchOne(
                'select coalesce(max(seq), 0) from opencode_workspace_turn '
                . 'where workspace_id = :workspace_id and id not in (:ids)',
                ['workspace_id' => $workspaceId, 'ids' => $ids],
                ['workspace_id' => ParameterType::STRING, 'ids' => ArrayParameterType::STRING]
            );

            $nomer = 0;
            $affected = 0;

            foreach ($turnRows as $row) {
                $newSeq = $orphanMax + (++$nomer);

                $affected += (int) $this->connection->executeStatement(
                    'insert into opencode_workspace_turn ('
                    . 'id, workspace_id, seq, workflow_id, status, request_text, answers_json, '
                    . 'result_json, error_key, model, author_account_id, credential_id, '
                    . 'author_display_name, created_at, finished_at'
                    . ') select '
                    . 't.id, t.session_id, :new_seq, t.workflow_id, t.status, t.request_text, '
                    . 't.answers_json, t.result_json, t.error_key, t.model, '
                    . "coalesce(nullif(t.author_account_id, ''), s.account_id), "
                    . "coalesce(nullif(t.credential_id, ''), s.credential_id), "
                    . "coalesce(t.author_display_name, ''), t.created_at, t.finished_at "
                    . 'from opencode_turn t '
                    . 'join opencode_session s on s.id = t.session_id '
                    . 'where t.id = :turn_id '
                    . 'on conflict (id) do update set '
                    . 'seq = excluded.seq, '
                    . 'status = excluded.status, '
                    . 'request_text = excluded.request_text, '
                    . 'answers_json = excluded.answers_json, '
                    . 'result_json = excluded.result_json, '
                    . 'error_key = excluded.error_key, '
                    . 'model = excluded.model, '
                    . 'author_account_id = excluded.author_account_id, '
                    . 'credential_id = excluded.credential_id, '
                    . 'author_display_name = excluded.author_display_name, '
                    . 'created_at = excluded.created_at, '
                    . 'finished_at = excluded.finished_at',
                    ['new_seq' => $newSeq, 'turn_id' => (string) $row['id']],
                    ['new_seq' => ParameterType::INTEGER, 'turn_id' => ParameterType::STRING]
                );

                $this->connection->executeStatement(
                    'insert into opencode_workspace_migration_journal '
                    . '(workspace_id, turn_id, old_seq, new_seq, created_at) '
                    . 'values (:workspace_id, :turn_id, :old_seq, :new_seq, :now) '
                    . 'on conflict (workspace_id, turn_id) do update set '
                    . 'old_seq = excluded.old_seq, new_seq = excluded.new_seq',
                    [
                        'workspace_id' => $workspaceId,
                        'turn_id' => (string) $row['id'],
                        'old_seq' => (int) $row['seq'],
                        'new_seq' => $newSeq,
                        'now' => time(),
                    ],
                    [
                        'workspace_id' => ParameterType::STRING,
                        'turn_id' => ParameterType::STRING,
                        'old_seq' => ParameterType::INTEGER,
                        'new_seq' => ParameterType::INTEGER,
                        'now' => ParameterType::INTEGER,
                    ]
                );
            }

            return $affected;
        });
    }

    private function perenestiUvedomleniya(): int
    {
        return (int) $this->connection->executeStatement(
            'insert into opencode_workspace_notification ('
            . 'id, account_id, workspace_id, turn_id, workspace_name, status, kind, '
            . 'created_at, read_at'
            . ') select '
            . 'n.id, n.account_id, n.session_id, n.turn_id, n.session_title, n.status, '
            . 'n.kind, n.created_at, n.read_at '
            . 'from opencode_notification n '
            . 'join opencode_session s on s.id = n.session_id '
            . 'join opencode_workspace_session ws on ws.id = n.session_id '
            . 'where s.tip = :tip '
            . 'on conflict (turn_id, account_id) do update set '
            . 'workspace_name = excluded.workspace_name, '
            . 'status = excluded.status, '
            . 'kind = excluded.kind, '
            . 'read_at = excluded.read_at',
            ['tip' => 'workspace']
        );
    }

    /**
     * @return list<string>
     */
    private function workspaceIdsSDublikatamiSeq(): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'select t.session_id from opencode_turn t '
            . 'join opencode_workspace_session ws on ws.id = t.session_id '
            . 'group by t.session_id, t.seq '
            . 'having count(*) > 1'
        );

        return array_values(array_unique(array_map('strval', $rows)));
    }
}
