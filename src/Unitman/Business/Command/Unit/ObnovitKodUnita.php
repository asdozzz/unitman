<?php

namespace App\Unitman\Business\Command\Unit;

final class ObnovitKodUnita
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
