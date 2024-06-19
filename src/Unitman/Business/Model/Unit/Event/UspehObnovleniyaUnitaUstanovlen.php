<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UspehObnovleniyaUnitaUstanovlen
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $steps,
        public readonly array $stateAsArray
    )
    {
    }

}
