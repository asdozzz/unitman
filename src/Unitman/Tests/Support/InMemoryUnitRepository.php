<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Unit\UnitRepository;

/**
 * Репозиторий юнитов в памяти для доменных use case тестов.
 */
final class InMemoryUnitRepository implements UnitRepository
{
    /**
     * @var array<string, Unit>
     */
    public array $units = [];

    public function getById(string $id): Unit
    {
        if (!array_key_exists($id, $this->units)) {
            throw new \DomainException('unit.not_found');
        }

        return $this->units[$id];
    }

    public function save(Unit $unit): void
    {
        $this->units[$unit->getId()] = $unit;
    }
}
