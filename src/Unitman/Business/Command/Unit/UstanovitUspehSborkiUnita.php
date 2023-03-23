<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitUspehSborkiUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textFromRunner,
        public readonly string $configText
    )
    {
    }

}
