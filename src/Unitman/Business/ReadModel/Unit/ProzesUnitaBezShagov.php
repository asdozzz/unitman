<?php

namespace App\Unitman\Business\ReadModel\Unit;

class ProzesUnitaBezShagov
{
    public function __construct(
        public readonly string $id,
        public readonly string $unitId,
        public readonly string $type,
        public readonly string $state,
    )
    {
    }

}
