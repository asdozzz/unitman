<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class OcheredUnitovReadModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $unitId,
        public readonly \DateTimeImmutable $lastUpdate,
    )
    {
    }

}
