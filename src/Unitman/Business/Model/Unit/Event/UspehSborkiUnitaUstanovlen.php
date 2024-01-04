<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UspehSborkiUnitaUstanovlen
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $textOtRunnera,
        public readonly ?array $configUnita,
        public readonly array $stateAsArray
    )
    {
    }

}
