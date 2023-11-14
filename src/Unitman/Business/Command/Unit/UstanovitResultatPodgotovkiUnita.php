<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitResultatPodgotovkiUnita
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }
}
