<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitOshibkuSborkiUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera
    )
    {
    }
}
