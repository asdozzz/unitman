<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class OcheredDlyProzesaUdaleniyaUnitaPosleZapuska
{
    const OSTANOVLEN = 'OSTANOVLEN';
    const SBROSHENA_PODGOTOVKA = 'SBROSHENA_PODGOTOVKA';

    const ERROR = 'ERROR';

    public function __construct(
        public readonly int $id,
        public readonly string $unitId,
        public readonly string $state,
    )
    {
    }

}
