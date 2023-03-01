<?php

namespace App\Unitman\Business\UseCase;

use App\Unitman\Business\Command\CheckAccessToRepo;
use App\Unitman\Business\Port\CanCheckAccessToRepo;
use App\Unitman\Business\Port\RepoRepository;
use App\Unitman\Business\Port\SecurityService;

final class CheckAccessToRepoUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private RepoRepository $repoRepository,
        private CanCheckAccessToRepo $canCheckAccessToRepo
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
