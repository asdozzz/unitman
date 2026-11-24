<?php

declare(strict_types=1);

namespace App\Runner\Tests\Support;

use App\Unitman\Business\Port\Unit\UnitRunnerRepository;

/**
 * In-memory привязки юнитов к раннерам для unit-тестов выбора.
 */
final class InMemoryUnitRunnerRepository implements UnitRunnerRepository
{
    /** @var array<string, string> unitId => runnerId */
    private array $bindings = [];

    /**
     * @param array<string, string> $bindings
     */
    public function __construct(array $bindings = [])
    {
        $this->bindings = $bindings;
    }

    public function findRunnerIdByUnitId(string $unitId): ?string
    {
        return $this->bindings[$unitId] ?? null;
    }

    public function bind(string $unitId, string $runnerId): void
    {
        // Жёсткая привязка: существующая не перезаписывается.
        $this->bindings[$unitId] ??= $runnerId;
    }

    public function unbind(string $unitId): void
    {
        unset($this->bindings[$unitId]);
    }

    public function isBoundToRunner(string $unitId, string $runnerId): bool
    {
        return ($this->bindings[$unitId] ?? null) === $runnerId;
    }

    public function countByRunnerId(string $runnerId): int
    {
        return count(array_filter(
            $this->bindings,
            static fn (string $bound): bool => $bound === $runnerId,
        ));
    }
}
