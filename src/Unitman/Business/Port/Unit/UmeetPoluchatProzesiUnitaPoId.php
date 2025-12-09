<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\ProzesUnitaBezShagov;

interface UmeetPoluchatProzesiUnitaPoId
{
    /**
     * @return ProzesUnitaBezShagov[]
     * */
    function poluchitProzesiPoIdUnita(string $unitId): array;

    function poluchitShagiZadachiPoId(string $prozesId, string $zadachaId): array;
}
