<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\CheckAccessToRepo;
use App\Unitman\Business\Port\Repo\CanCheckAccessToRepo;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class CheckAccessToRepoUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository         $repoRepository,
        private CanCheckAccessToRepo   $canCheckAccessToRepo
    )
    {
    }

    function handle(CheckAccessToRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repo = $this->repoRepository->getById($command->repoId);
        $checkAccessReponse = $this->canCheckAccessToRepo->checkAccess($repo);
        if (!$checkAccessReponse->success) {
            throw new \DomainException($checkAccessReponse->message);
        }
        $repo->accessConfirm();
        $this->repoRepository->save($repo);
    }
}
