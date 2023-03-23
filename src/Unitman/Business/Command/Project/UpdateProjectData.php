<?php

namespace App\Unitman\Business\Command\Project;

final class UpdateProjectData
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $newProjectName
    )
    {
    }

}
