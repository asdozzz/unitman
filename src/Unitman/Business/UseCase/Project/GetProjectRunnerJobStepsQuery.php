<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectRunnerJobSteps;
use App\Unitman\Business\Port\Project\CanGetProjectRunnerJobs;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;

final class GetProjectRunnerJobStepsQuery
{
    public function __construct(private CanGetProjectRunnerJobs $canGetProjectRunnerJobs)
    {
    }

    function handle(GetProjectRunnerJobSteps $command): ProjectRunnerJob
    {
        return $this->canGetProjectRunnerJobs->getStepsByJobId($command->id);
    }
}
