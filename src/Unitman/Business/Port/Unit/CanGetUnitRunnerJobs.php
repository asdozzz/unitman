<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\UnitRunnerJob;

interface CanGetUnitRunnerJobs
{
    /**
     * @return UnitRunnerJob[]
     * */
    function findAllRunnerJobsByUnitId(string $unitId): array;
}
