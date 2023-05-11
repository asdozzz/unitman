<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class PeremenieUnitaZapolneni
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $values,
        public readonly array $stateAsArray
    )
    {
    }
}
