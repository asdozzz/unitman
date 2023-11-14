<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitResultatZapuska
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
