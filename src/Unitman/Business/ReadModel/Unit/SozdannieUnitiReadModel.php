<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Результат группового создания юнитов.
 */
final class SozdannieUnitiReadModel
{
    /**
     * @param SozdanniyUnitReadModel[] $units
     */
    public function __construct(
        public readonly array $units,
    ) {
    }

    /**
     * @return array{units: array<int, array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'units' => array_map(
                static fn(SozdanniyUnitReadModel $unit): array => $unit->toArray(),
                $this->units,
            ),
        ];
    }
}
