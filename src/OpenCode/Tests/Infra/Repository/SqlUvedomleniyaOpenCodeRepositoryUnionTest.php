<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * API уведомлений в переходный период читает union двух таблиц с
 * дедупликацией по `(turn_id, account_id)` и переносит `read_at` в обе.
 */
final class SqlUvedomleniyaOpenCodeRepositoryUnionTest extends TestCase
{
    #[Test]
    public function chtenie_obedinyaet_dve_tablicy_bez_dublei(): void
    {
        $zapros = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(
                static function (string $sql, array $params) use (&$zapros): array {
                    $zapros = $sql;

                    return [];
                }
            );

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);
        $repository->findNeprochitannie('acc-1', 20);

        self::assertStringContainsString('from opencode_notification', $zapros);
        self::assertStringContainsString('from opencode_workspace_notification w', $zapros);
        self::assertStringContainsString('union all', $zapros);
        self::assertStringContainsString('not exists', $zapros);
        self::assertStringContainsString('n.turn_id = w.turn_id and n.account_id = w.account_id', $zapros);
        self::assertStringContainsString('read_at is null', $zapros);
        self::assertStringContainsString('order by created_at', $zapros);
        self::assertStringContainsString('limit :limit', $zapros);
    }

    #[Test]
    public function otmetka_o_prochtenii_pishetsya_v_obe_tablicy(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['n-1']);

        $updates = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$updates): int {
                    $updates[] = $sql;

                    return 0;
                }
            );

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);
        $ids = $repository->zahvatitNeprochitannie('acc-1', ['n-1'], 1_700_000_000);

        self::assertSame(['n-1'], $ids);
        self::assertCount(1, $updates);
        self::assertStringContainsString('update opencode_workspace_notification set read_at', $updates[0]);
        self::assertStringContainsString('read_at is null', $updates[0]);
    }
}
