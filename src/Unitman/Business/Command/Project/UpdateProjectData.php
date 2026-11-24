<?php

namespace App\Unitman\Business\Command\Project;

final class UpdateProjectData
{
    public function __construct(
        public readonly string $id,
        public readonly string $newProjectName,
        public readonly int $memoryLimit
    )
    {
    }

}
