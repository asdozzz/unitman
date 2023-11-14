<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectWasAdded
{
    public function __construct(
        public readonly string $id,
        public readonly string $repoId,
        public readonly string $projectCode,
        public readonly string $projectName,
        public readonly string $mainBranch,
    )
    {
    }

}
