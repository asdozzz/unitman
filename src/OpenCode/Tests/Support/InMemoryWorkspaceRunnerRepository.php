<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Port\WorkspaceRunnerRepository;

/**
 * In-memory привязки workspace к раннерам для unit-тестов.
 */
final class InMemoryWorkspaceRunnerRepository implements WorkspaceRunnerRepository
{
    /** @var array<string, string> workspaceId => runnerId */
    private array $bindings = [];

    /**
     * @param array<string, string> $bindings
     */
    public function __construct(array $bindings = [])
    {
        $this->bindings = $bindings;
    }

    public function findRunnerIdByWorkspaceId(string $workspaceId): ?string
    {
        return $this->bindings[$workspaceId] ?? null;
    }

    public function bind(string $workspaceId, string $runnerId): void
    {
        // Жёсткая привязка: существующая не перезаписывается.
        $this->bindings[$workspaceId] ??= $runnerId;
    }

    public function unbind(string $workspaceId): void
    {
        unset($this->bindings[$workspaceId]);
    }

    public function isBoundToRunner(string $workspaceId, string $runnerId): bool
    {
        return ($this->bindings[$workspaceId] ?? null) === $runnerId;
    }

    public function countByRunnerId(string $runnerId): int
    {
        return count(array_filter(
            $this->bindings,
            static fn (string $bound): bool => $bound === $runnerId,
        ));
    }
}
