<?php

declare(strict_types=1);

namespace App\Runner\Tests\Support;

use App\Runner\Business\Model\ProjectBuildState;
use App\Runner\Business\Port\ProjectBuildStateRepository;

/**
 * In-memory `project_build_state` для unit-тестов build-state API.
 *
 * Повторяет ключевую семантику SQL-репозитория: составной ключ
 * `(runner_id, project_id)` и upsert без затрагивания чужих записей.
 */
final class InMemoryProjectBuildStateRepository implements ProjectBuildStateRepository
{
    /** @var array<string, ProjectBuildState> */
    private array $states = [];

    public function find(string $runnerId, string $projectId): ?ProjectBuildState
    {
        return $this->states[$this->key($runnerId, $projectId)] ?? null;
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
        $state = new ProjectBuildState(
            $runnerId,
            $projectId,
            $builtAt ?? time(),
            $commit,
            $branch,
        );

        $this->states[$this->key($runnerId, $projectId)] = $state;

        return $state;
    }

    public function remove(string $runnerId, string $projectId): void
    {
        unset($this->states[$this->key($runnerId, $projectId)]);
    }

    public function count(): int
    {
        return count($this->states);
    }

    private function key(string $runnerId, string $projectId): string
    {
        return $runnerId . "\0" . $projectId;
    }
}
