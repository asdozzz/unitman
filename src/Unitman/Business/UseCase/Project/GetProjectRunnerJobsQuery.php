<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectRunnerJobs;
use App\Unitman\Infra\Repository\Project\ProjectRunnerJobRepository;

final class GetProjectRunnerJobsQuery
{
    public function __construct(private ProjectRunnerJobRepository $projectRunnerJobRepository)
    {
    }

    function handle(GetProjectRunnerJobs $command): array
    {
        return $this->projectRunnerJobRepository->findAllRunnerJobsByProjectId($command->id);
    }
}
