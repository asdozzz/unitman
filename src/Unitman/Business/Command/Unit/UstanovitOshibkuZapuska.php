<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitOshibkuZapuska
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera
    )
    {
    }
}
