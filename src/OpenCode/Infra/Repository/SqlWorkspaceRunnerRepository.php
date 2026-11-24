<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Port\WorkspaceRunnerRepository;
use Doctrine\DBAL\Connection;

final class SqlWorkspaceRunnerRepository implements WorkspaceRunnerRepository
{
    private const TABLE = 'workspace_runner';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findRunnerIdByWorkspaceId(string $workspaceId): ?string
    {
        $table = self::TABLE;
        $runnerId = $this->connection->fetchOne(
            "select runner_id from $table where workspace_id = ?",
            [$workspaceId],
        );

        return $runnerId !== false ? (string) $runnerId : null;
    }

    public function bind(string $workspaceId, string $runnerId): void
    {
        $table = self::TABLE;

        // `do nothing` намеренно: жёсткая привязка не переносится на другой раннер.
        $this->connection->executeStatement(
            "insert into $table (workspace_id, runner_id, created_at)
             values (?, ?, ?)
             on conflict (workspace_id) do nothing",
            [$workspaceId, $runnerId, time()],
        );
    }

    public function unbind(string $workspaceId): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "delete from $table where workspace_id = ?",
            [$workspaceId],
        );
    }

    public function isBoundToRunner(string $workspaceId, string $runnerId): bool
    {
        $table = self::TABLE;
        $bound = $this->connection->fetchOne(
            "select 1 from $table where workspace_id = ? and runner_id = ?",
            [$workspaceId, $runnerId],
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
