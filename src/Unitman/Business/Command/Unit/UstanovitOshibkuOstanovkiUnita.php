<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitOshibkuOstanovkiUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera
    )
    {
    }
}
