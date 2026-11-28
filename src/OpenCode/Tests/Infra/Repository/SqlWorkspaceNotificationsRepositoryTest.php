<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceNotificationsRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlWorkspaceNotificationsRepositoryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function save_idempotenten_po_turn_i_poluchatelyu(): void
    {
        $zapros = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$zapros): int {
                $zapros = ['sql' => $sql, 'params' => $params];

                return 1;
            });

        $saved = (new SqlWorkspaceNotificationsRepository($connection))->save($this->notification());

        self::assertTrue($saved);
        self::assertNotNull($zapros);
        self::assertStringContainsString('opencode_workspace_notification', $zapros['sql']);
        self::assertStringContainsString('on conflict (turn_id, account_id)', $zapros['sql']);
        self::assertSame('workspace-1', $zapros['params']['workspace_id']);
    }

    #[Test]
    public function povtornaya_vstavka_ne_publikuetsya(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(0);

        self::assertFalse((new SqlWorkspaceNotificationsRepository($connection))->save($this->notification()));
    }

    private function notification(): UvedomlenieONovomSoobsheniiOpenCode
    {
        return new UvedomlenieONovomSoobsheniiOpenCode(
            'notification-1',
            'acc-2',
            'workspace-1',
            'turn-1',
            'payments-refactor',
            'ANSWERED',
            'ANSWER',
            self::NOW,
            'workspace',
        );
    }
}
