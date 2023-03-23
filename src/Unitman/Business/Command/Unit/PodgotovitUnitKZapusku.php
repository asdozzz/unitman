<?php

namespace App\Unitman\Business\Command\Unit;

final class PodgotovitUnitKZapusku
{
    public function __construct(
        public readonly string $unitId
    )
    {
    }

}
