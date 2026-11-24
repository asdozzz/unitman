<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

/**
 * Жёсткая привязка юнита к раннеру (multi-runner).
 *
 * Контракт: plans/runner-multi-runner.md, §5.1 и §7. Юнит никогда не
 * переносится автоматически: повторный `bind` не меняет существующую привязку.
 */
interface UnitRunnerRepository
{
    public function findRunnerIdByUnitId(string $unitId): ?string;

    /**
     * Идемпотентная привязка. Если юнит уже привязан к другому раннеру,
     * привязка не меняется (жёсткая привязка).
     */
    public function bind(string $unitId, string $runnerId): void;

    public function unbind(string $unitId): void;

    public function isBoundToRunner(string $unitId, string $runnerId): bool;

    public function countByRunnerId(string $runnerId): int;
}
