<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\ProjectBuildState;

/**
 * Состояние сборки проекта per-runner.
 *
 * Контракт: plans/runner-multi-runner.md, §3 и §5.3.
 */
interface ProjectBuildStateRepository
{
    public function find(string $runnerId, string $projectId): ?ProjectBuildState;

    public function isBuilt(string $runnerId, string $projectId): bool;

    /**
     * Идемпотентная запись факта сборки (upsert по `(runner_id, project_id)`).
     */
    public function markBuilt(
        string $runnerId,
        string $projectId,
        ?string $commit = null,
        ?string $branch = null,
        ?int $builtAt = null,
    ): ProjectBuildState;

    public function remove(string $runnerId, string $projectId): void;
}
