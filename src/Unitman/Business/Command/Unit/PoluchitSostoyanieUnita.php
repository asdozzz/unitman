<?php

namespace App\Unitman\Business\Command\Unit;

readonly class PoluchitSostoyanieUnita
{
    public function __construct(
        public string $unitId,
    )
    {
    }
}
