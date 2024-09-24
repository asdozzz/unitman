<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitRunnerJobSteps;
use App\Unitman\Business\Port\Unit\CanGetUnitRunnerJobs;
use App\Unitman\Business\ReadModel\Unit\UnitRunnerJob;

final class GetUnitRunnerJobStepsQuery
{
    public function __construct(private CanGetUnitRunnerJobs $canGetUnitRunnerJobs)
    {
    }

    function handle(GetUnitRunnerJobSteps $command): UnitRunnerJob
    {
        return $this->canGetUnitRunnerJobs->getStepsByJobId($command->id);
    }
}
