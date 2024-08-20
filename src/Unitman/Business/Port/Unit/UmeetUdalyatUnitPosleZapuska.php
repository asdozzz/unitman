<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Model\Runner\JobId;

interface UmeetUdalyatUnitPosleZapuska
{
    function udalitUnitPosleZapuska(string $unitId): JobId;
}
