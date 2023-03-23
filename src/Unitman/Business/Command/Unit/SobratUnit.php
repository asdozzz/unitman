<?php

namespace App\Unitman\Business\Command\Unit;

final class SobratUnit
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
