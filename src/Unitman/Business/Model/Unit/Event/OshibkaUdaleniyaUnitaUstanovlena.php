<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class OshibkaUdaleniyaUnitaUstanovlena
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $steps,
        public readonly array $stateAsArray
    )
    {
    }

}
