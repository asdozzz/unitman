<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJobWithoutSteps;

interface CanGetProjectRunnerJobs
{
    /**
     * @return ProjectRunnerJobWithoutSteps[]
     * */
    function findAllRunnerJobsByProjectId(string $projectId): array;

    function getStepsByJobId(string $jobId): ProjectRunnerJob;
}
