<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Migration;

use App\OpenCode\Infra\Migration\WorkspaceAggregateRollbackSync;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Обратный backfill пишет только в легаси-таблицы, идемпотентно и не удаляет
 * данные. Окно `cutover_ts` применяется к сессиям и дочерним строкам.
 */
final class WorkspaceAggregateRollbackSyncTest extends TestCase
{
    #[Test]
    public function perenosit_v_legasi_tolko_vstavki_s_oknom_cutover(): void
    {
        $sql = [];
        $params = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(
            static fn (callable $closure) => $closure()
        );
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $statement, array $bindings) use (&$sql, &$params): int {
                $sql[] = $statement;
                $params[] = $bindings;

                return 1;
            }
        );

        $report = (new WorkspaceAggregateRollbackSync($connection))->run(1_700_000_000);

        self::assertCount(7, $sql);
        self::assertSame(7, array_sum($report));

        foreach ($sql as $statement) {
            self::assertStringStartsWith('insert into opencode_', $statement);
            self::assertStringNotContainsString('delete from', strtolower($statement));
        }

        self::assertStringContainsString('opencode_session (', $sql[0]);
        self::assertStringContainsString(':tip', $sql[0]);
        self::assertStringContainsString('ws.updated_at > :cutover_ts', $sql[0]);

        self::assertStringContainsString('opencode_workspace (', $sql[1]);
        self::assertStringContainsString('opencode_turn (', $sql[5]);
        self::assertStringContainsString('coalesce(excluded.read_at, opencode_notification.read_at)', $sql[6]);
        self::assertStringContainsString('on conflict (turn_id, account_id)', $sql[6]);

        foreach ($params as $bindings) {
            self::assertSame(1_700_000_000, $bindings['cutover_ts']);
        }
    }
}
