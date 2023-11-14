<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitResultatUdaleniya
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
