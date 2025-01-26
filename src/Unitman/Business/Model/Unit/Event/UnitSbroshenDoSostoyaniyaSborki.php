<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UnitSbroshenDoSostoyaniyaSborki
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $stateAsArray
    )
    {
    }
}
