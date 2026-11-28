<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceTurnRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Резервация workspace на общей таблице `opencode_session_running_turn`.
 */
final class SqlWorkspaceTurnRepositoryRezervaciyaTest extends TestCase
{
    #[Test]
    public function svezhiy_zahvat_blokiruet_vtoroi_hod(): void
    {
        $zapros = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$zapros): int {
                $zapros = ['sql' => $sql, 'params' => $params];

                return 0;
            });

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.busy');

        try {
            (new SqlWorkspaceTurnRepository($connection))
                ->zanyatSessiyu('workspace-1', 'turn-1', 1_000_000, 900);
        } finally {
            self::assertNotNull($zapros);
            self::assertStringContainsString('opencode_session_running_turn', $zapros['sql']);
            self::assertStringContainsString('created_at < :stale_before', $zapros['sql']);
            self::assertSame(1_000_000 - 900, $zapros['params']['stale_before']);
        }
    }

    #[Test]
    public function osvobozhdenie_trebuet_sovpadeniya_turn_id(): void
    {
        $zapros = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $criteria, array $types) use (&$zapros): int {
                $zapros = ['table' => $table, 'criteria' => $criteria, 'types' => $types];

                return 1;
            });

        (new SqlWorkspaceTurnRepository($connection))->osvoboditSessiyu('workspace-1', 'turn-1');

        self::assertSame('opencode_session_running_turn', $zapros['table']);
        self::assertSame(['session_id' => 'workspace-1', 'turn_id' => 'turn-1'], $zapros['criteria']);
        self::assertSame(ParameterType::STRING, $zapros['types']['turn_id']);
    }

    #[Test]
    public function prosrochennaya_arenda_ne_schitaetsya_zanyatostyu(): void
    {
        $zapros = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params) use (&$zapros): mixed {
                $zapros = ['sql' => $sql, 'params' => $params];

                return false;
            });

        $zanyata = (new SqlWorkspaceTurnRepository($connection))
            ->zanyata('workspace-1', 1_000_000, 900);

        self::assertFalse($zanyata);
        self::assertStringContainsString('created_at >= :fresh_after', $zapros['sql']);
        self::assertSame(1_000_000 - 900, $zapros['params']['fresh_after']);
    }
}
