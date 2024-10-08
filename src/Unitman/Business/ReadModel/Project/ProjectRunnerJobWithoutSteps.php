<?php

namespace App\Unitman\Business\ReadModel\Project;

class ProjectRunnerJobWithoutSteps
{
    /**
     * @param string $id
     * @param string $projectId
     * @param string $jobType
     * @param bool $success
     */
    public function __construct(
        public string $id,
        public readonly string $projectId,
        public readonly string $jobType,
        public readonly bool $success,
    )
    {
    }
}
