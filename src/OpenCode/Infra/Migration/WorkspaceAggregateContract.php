<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Migration;

use Doctrine\DBAL\Connection;

/**
 * Contract-шаг: удаление легаси workspace-данных после bake-периода.
 *
 * Удаляет только workspace-строки легаси-агрегата и никогда не трогает
 * unit-сессии (`tip = 'unit'`), `opencode_session` unit-строки и таблицы
 * `opencode_unit_session*`. Новый агрегат (`opencode_workspace_session*`)
 * остаётся источником истины.
 *
 * `plan()` — read-only, используется в dry-run. `run()` выполняется в одной
 * транзакции в порядке «дети → родитель», чтобы внешние ключи не мешали.
 */
final class WorkspaceAggregateContract
{
    private const WORKSPACE_TIP = 'workspace';

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkspaceAggregateParityChecker $checker,
    ) {
    }

    /**
     * Что будет удалено. Read-only.
     *
     * @return array<string, int>
     */
    public function plan(): array
    {
        return [
            'opencode_session (tip=workspace)' => $this->chislo(
                'select count(*) from opencode_session where tip = :tip',
                ['tip' => self::WORKSPACE_TIP],
            ),
            'opencode_workspace' => $this->chislo('select count(*) from opencode_workspace'),
            'opencode_workspace_project' => $this->chislo('select count(*) from opencode_workspace_project'),
            'opencode_workspace_update' => $this->chislo('select count(*) from opencode_workspace_update'),
            'opencode_workspace_unit' => $this->chislo(
                'select count(*) from opencode_workspace_unit u '
                . 'where exists (select 1 from opencode_session s '
                . 'where s.id = u.workspace_id and s.tip = :tip)',
                ['tip' => self::WORKSPACE_TIP],
            ),
            'opencode_turn (workspace)' => $this->chislo(
                'select count(*) from opencode_turn t '
                . 'where exists (select 1 from opencode_session s '
                . 'where s.id = t.session_id and s.tip = :tip)',
                ['tip' => self::WORKSPACE_TIP],
            ),
            'opencode_notification (workspace)' => $this->chislo(
                'select count(*) from opencode_notification n '
                . 'where exists (select 1 from opencode_session s '
                . 'where s.id = n.session_id and s.tip = :tip)',
                ['tip' => self::WORKSPACE_TIP],
            ),
            'opencode_session_participant (workspace)' => $this->chislo(
                'select count(*) from opencode_session_participant p '
                . 'where exists (select 1 from opencode_session s '
                . 'where s.id = p.session_id and s.tip = :tip)',
                ['tip' => self::WORKSPACE_TIP],
            ),
            'opencode_workspace_session (останется)' => $this->chislo(
                'select count(*) from opencode_workspace_session',
            ),
            'opencode_session (tip=unit, останется)' => $this->chislo(
                'select count(*) from opencode_session where tip <> :tip',
                ['tip' => self::WORKSPACE_TIP],
            ),
        ];
    }

    /**
     * Проверки, без которых удаление запрещено.
     *
     * 1. Гейт сверки: легаси и новый агрегат совпадают.
     * 2. Совместимость с rollback-sync: каждая строка нового агрегата имеет
     *    пару в легаси (`opencode_session` tip=workspace + `opencode_workspace`),
     *    то есть обратный перенос возможен без потерь.
     *
     * @return list<string>
     */
    public function proveritBezopasnost(): array
    {
        $problems = [];

        $parity = $this->checker->proverit();

        if (!$parity['ok']) {
            foreach ($parity['checks'] as $group => $values) {
                foreach ($values as $name => $count) {
                    if ($count > 0) {
                        $problems[] = sprintf('parity: %s.%s = %d', $group, $name, $count);
                    }
                }
            }
        }

        $bezPary = $this->chislo(
            'select count(*) from opencode_workspace_session ws '
            . 'left join opencode_session s on s.id = ws.id and s.tip = :tip '
            . 'left join opencode_workspace w on w.session_id = ws.id '
            . 'where s.id is null or w.session_id is null',
            ['tip' => self::WORKSPACE_TIP],
        );

        if ($bezPary > 0) {
            $problems[] = sprintf(
                'rollback-sync: %d workspace нового агрегата не имеют пары в легаси',
                $bezPary,
            );
        }

        return $problems;
    }

    /**
     * Удаляет workspace-строки легаси. Unit-сессии не затрагиваются.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        return $this->connection->transactional(function (): array {
            $workspaceIds = 'select id from opencode_session where tip = :tip';
            $params = ['tip' => self::WORKSPACE_TIP];

            return [
                'opencode_session_participant' => (int) $this->connection->executeStatement(
                    'delete from opencode_session_participant where session_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_notification' => (int) $this->connection->executeStatement(
                    'delete from opencode_notification where session_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_turn' => (int) $this->connection->executeStatement(
                    'delete from opencode_turn where session_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_workspace_unit' => (int) $this->connection->executeStatement(
                    'delete from opencode_workspace_unit where workspace_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_workspace_update' => (int) $this->connection->executeStatement(
                    'delete from opencode_workspace_update where session_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_workspace_project' => (int) $this->connection->executeStatement(
                    'delete from opencode_workspace_project where session_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_workspace' => (int) $this->connection->executeStatement(
                    'delete from opencode_workspace where session_id in (' . $workspaceIds . ')',
                    $params,
                ),
                'opencode_session' => (int) $this->connection->executeStatement(
                    'delete from opencode_session where tip = :tip',
                    $params,
                ),
            ];
        });
    }

    /**
     * @param array<string, mixed> $params
     */
    private function chislo(string $sql, array $params = []): int
    {
        return (int) $this->connection->fetchOne($sql, $params);
    }
}
