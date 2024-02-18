<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PoluchitSpisokVetokProekta;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokVetokProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\ReadModel\VetkaProekta;

final class PoluchitSpisokVetokProektaQuery
{
    public function __construct(
        private UmeetPoluchatSpisokVetokProekta $umeetPoluchatSpisokVetokProekta,
        private ProjectRepository $projectRepository,
        private RepoRepository $repoRepository
    )
    {
    }

    /**
     * @return VetkaProekta[]
     * */
    function handle(PoluchitSpisokVetokProekta $command): array
    {
        $project = $this->projectRepository->getById($command->id);
        $repo = $this->repoRepository->getById($project->getRepoId());
        return $this->umeetPoluchatSpisokVetokProekta->poluchitVetkiProekta($repo, $project->getCode());
    }
}
