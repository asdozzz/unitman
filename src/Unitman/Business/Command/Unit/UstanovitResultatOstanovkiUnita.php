<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitResultatOstanovkiUnita
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
