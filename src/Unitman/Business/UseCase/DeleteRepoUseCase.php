<?php

namespace App\Unitman\Business\UseCase;

use App\Unitman\Business\Command\DeleteRepo;
use App\Unitman\Business\Port\RepoRepository;
use App\Unitman\Business\Port\SecurityService;

final class DeleteRepoUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private RepoRepository $repoRepository
    )
    {
    }

    function handle(DeleteRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repo = $this->repoRepository->getById($command->repoId);
        $repo->delete();
        $this->repoRepository->save($repo);
    }
}
