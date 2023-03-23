<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitOshibkuSbrosaPodgotovkiUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera
    )
    {
    }
}
