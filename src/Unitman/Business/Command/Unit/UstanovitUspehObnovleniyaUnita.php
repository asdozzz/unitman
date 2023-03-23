<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitUspehObnovleniyaUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textFromRunner,
        public readonly string $configText
    )
    {
    }
}
