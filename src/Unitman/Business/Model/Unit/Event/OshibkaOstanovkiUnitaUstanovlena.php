<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class OshibkaOstanovkiUnitaUstanovlena
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera,
        public readonly array $stateAsArray
    )
    {
    }

}
