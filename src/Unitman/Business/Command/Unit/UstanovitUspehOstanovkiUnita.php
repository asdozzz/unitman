<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitUspehOstanovkiUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textFromRunner,
    )
    {
    }
}
