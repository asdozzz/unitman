<?php

namespace App\Unitman\Business\Command\Unit;

final class UdalitUnit
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
