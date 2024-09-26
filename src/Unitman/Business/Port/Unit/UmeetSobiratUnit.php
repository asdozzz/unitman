<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Model\Runner\JobId;

interface UmeetSobiratUnit
{
    function sobratUnitOtLizaSystemi(string $unitId): JobId;
}
