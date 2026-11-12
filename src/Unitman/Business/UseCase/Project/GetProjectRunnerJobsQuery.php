<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectRunnerJobs;
use App\Unitman\Business\Port\Project\CanGetProjectRunnerJobs;

final class GetProjectRunnerJobsQuery
{
    public function __construct(private CanGetProjectRunnerJobs $canGetProjectRunnerJobs)
    {
    }

    function handle(GetProjectRunnerJobs $command): array
    {
        return $this->canGetProjectRunnerJobs->findAllRunnerJobsByProjectId($command->id);
    }
}
