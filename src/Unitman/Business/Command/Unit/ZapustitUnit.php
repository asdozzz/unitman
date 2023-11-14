<?php

namespace App\Unitman\Business\Command\Unit;

final class ZapustitUnit
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
