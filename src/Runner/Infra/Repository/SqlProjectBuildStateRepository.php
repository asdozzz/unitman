<?php

declare(strict_types=1);

namespace App\Runner\Infra\Repository;

use App\Runner\Business\Model\ProjectBuildState;
use App\Runner\Business\Port\ProjectBuildStateRepository;
use Doctrine\DBAL\Connection;

final class SqlProjectBuildStateRepository implements ProjectBuildStateRepository
{
    private const TABLE = 'project_build_state';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(string $runnerId, string $projectId): ?ProjectBuildState
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select runner_id, project_id, built_at, commit_sha, branch
             from $table
             where runner_id = ? and project_id = ?",
            [$runnerId, $projectId],
        );

        if ($row === false) {
            return null;
        }

        return new ProjectBuildState(
            runnerId: (string) $row['runner_id'],
            projectId: (string) $row['project_id'],
            builtAt: (int) $row['built_at'],
            commit: $row['commit_sha'] !== null ? (string) $row['commit_sha'] : null,
            branch: $row['branch'] !== null ? (string) $row['branch'] : null,
        );
    }

    public function isBuilt(string $runnerId, string $projectId): bool
    {
        return $this->find($runnerId, $projectId) !== null;
    }

    public function markBuilt(
        string $runnerId,
        string $projectId,
        ?string $commit = null,
        ?string $branch = null,
        ?int $builtAt = null,
    ): ProjectBuildState {
        $builtAt ??= time();
        $table = self::TABLE;

        $this->connection->executeStatement(
            "insert into $table (runner_id, project_id, built_at, commit_sha, branch)
             values (:runnerId, :projectId, :builtAt, :commit, :branch)
             on conflict (runner_id, project_id) do update
                 set built_at = excluded.built_at,
                     commit_sha = excluded.commit_sha,
                     branch = excluded.branch",
            [
                'runnerId' => $runnerId,
                'projectId' => $projectId,
                'builtAt' => $builtAt,
                'commit' => $commit,
                'branch' => $branch,
            ],
        );

        return new ProjectBuildState($runnerId, $projectId, $builtAt, $commit, $branch);
    }

    public function remove(string $runnerId, string $projectId): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "delete from $table where runner_id = ? and project_id = ?",
            [$runnerId, $projectId],
        );
    }
}
