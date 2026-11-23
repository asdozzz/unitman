<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Результат создания одного юнита в групповой операции.
 */
final class SozdanniyUnitReadModel
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
    ) {
    }

    /**
     * @return array{unitId: string, projectId: string, unitName: string, branch: string}
     */
    public function toArray(): array
    {
        return [
            'unitId' => $this->unitId,
            'projectId' => $this->projectId,
            'unitName' => $this->unitName,
            'branch' => $this->branch,
        ];
    }
}
