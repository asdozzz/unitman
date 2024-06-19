<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class KonfigUnitaUstanovlen
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $configUnita
    )
    {
    }

}
