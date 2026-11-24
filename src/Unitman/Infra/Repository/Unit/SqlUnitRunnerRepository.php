<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Port\Unit\UnitRunnerRepository;
use Doctrine\DBAL\Connection;

final class SqlUnitRunnerRepository implements UnitRunnerRepository
{
    private const TABLE = 'unit_runner';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findRunnerIdByUnitId(string $unitId): ?string
    {
        $table = self::TABLE;
        $runnerId = $this->connection->fetchOne(
            "select runner_id from $table where unit_id = ?",
            [$unitId],
        );

        return $runnerId !== false ? (string) $runnerId : null;
    }

    public function bind(string $unitId, string $runnerId): void
    {
        $table = self::TABLE;

        // `do nothing` намеренно: жёсткая привязка не переносится на другой раннер.
        $this->connection->executeStatement(
            "insert into $table (unit_id, runner_id, created_at)
             values (?, ?, ?)
             on conflict (unit_id) do nothing",
            [$unitId, $runnerId, time()],
        );
    }

    public function unbind(string $unitId): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement("delete from $table where unit_id = ?", [$unitId]);
    }

    public function isBoundToRunner(string $unitId, string $runnerId): bool
    {
        $table = self::TABLE;
        $bound = $this->connection->fetchOne(
            "select 1 from $table where unit_id = ? and runner_id = ?",
            [$unitId, $runnerId],
        );

        return $bound !== false;
    }

    public function countByRunnerId(string $runnerId): int
    {
        $table = self::TABLE;

        return (int) $this->connection->fetchOne(
            "select count(*) from $table where runner_id = ?",
            [$runnerId],
        );
    }
}
