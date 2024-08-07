<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class OcheredDlyProzesaObnovleniyaKodaPosleZapuska
{
    const OSTANOVLEN = 'OSTANOVLEN';
    const SBROSHENA_PODGOTOVKA = 'SBROSHENA_PODGOTOVKA';
    const OBNOVLEN = 'OBNOVLEN';
    const PODGOTOVLEN = 'PODGOTOVLEN';

    const ERROR = 'ERROR';

    public function __construct(
        public readonly int $id,
        public readonly string $unitId,
        public readonly string $state,
    )
    {
    }

}
