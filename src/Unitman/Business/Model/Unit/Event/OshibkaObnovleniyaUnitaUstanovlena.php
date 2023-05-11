<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class OshibkaObnovleniyaUnitaUstanovlena
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera,
        public readonly array $stateAsArray
    )
    {
    }

}
