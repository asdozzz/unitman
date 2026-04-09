<?php

namespace App\Unitman\Business\Command\Project;

final class AddProject
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $projectCode,
        public readonly string $projectName,
        public readonly string $mainBranch = 'master',
        public readonly string $proxyHost = '',
        public readonly int $memoryLimit = 0
    )
    {
    }

}
