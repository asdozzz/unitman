<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitResultatObnovleniyaUnita
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
