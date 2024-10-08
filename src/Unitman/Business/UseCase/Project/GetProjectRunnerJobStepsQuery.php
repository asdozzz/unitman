<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectRunnerJobSteps;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;
use App\Unitman\Infra\Repository\Project\ProjectRunnerJobRepository;

final class GetProjectRunnerJobStepsQuery
{
    public function __construct(private ProjectRunnerJobRepository $projectRunnerJobRepository)
    {
    }

    function handle(GetProjectRunnerJobSteps $command): ProjectRunnerJob
    {
        return $this->projectRunnerJobRepository->getStepsByJobId($command->id);
    }
}
