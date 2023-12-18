<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Command\Repo\DeleteRepo;
use App\Unitman\Business\Port\CanGetProjectList;
use App\Unitman\Business\Port\RepoRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class DeleteRepoUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository         $repoRepository,
        private CanGetProjectList $canGetProjectList
    )
    {
    }

    function handle(DeleteRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $proekti = $this->canGetProjectList->getListByRepoId($command->repoId);

        if (!empty($proekti)) {
            throw new \Exception('repo.delete.est_proekti');
        }

        $repo = $this->repoRepository->getById($command->repoId);
        $repo->delete();
        $this->repoRepository->save($repo);
    }
}
