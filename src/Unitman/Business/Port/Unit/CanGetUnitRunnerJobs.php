<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\UnitRunnerJob;
use App\Unitman\Business\ReadModel\Unit\UnitRunnerJobWithoutSteps;

interface CanGetUnitRunnerJobs
{
    /**
     * @return UnitRunnerJobWithoutSteps[]
     * */
    function findAllRunnerJobsByUnitId(string $unitId): array;

    function getStepsByJobId(string $jobId): UnitRunnerJob;
}
