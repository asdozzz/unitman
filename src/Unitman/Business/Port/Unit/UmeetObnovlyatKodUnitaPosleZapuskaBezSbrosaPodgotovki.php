<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Model\Runner\JobId;

interface UmeetObnovlyatKodUnitaPosleZapuskaBezSbrosaPodgotovki
{
    function obnovitKodUnita(string $unitId): JobId;
}
