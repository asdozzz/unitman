<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UspehZapuskaUnitaUstanovlen
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $steps,
        public readonly string $url,
        public readonly array $stateAsArray
    )
    {
    }

}
