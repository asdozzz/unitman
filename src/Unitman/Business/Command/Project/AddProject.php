<?php

namespace App\Unitman\Business\Command\Project;

final class AddProject
{
    /**
     * @param string[] $zashishennieVetki
     */
    public function __construct(
        public readonly string $repoId,
        public readonly string $projectCode,
        public readonly string $projectName,
        public readonly string $mainBranch = 'master',
        public readonly int $memoryLimit = 0,
        public readonly array $zashishennieVetki = []
    )
    {
    }

}
