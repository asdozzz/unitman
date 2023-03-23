<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Port\RepoRepository;
use App\Unitman\Business\Port\SecurityService;

final class ChangeCredentialsOfRepoUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private RepoRepository $repoRepository
    )
    {
    }

    function handle(ChangeCredentialsOfRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repo = $this->repoRepository->getById($command->repoId);
        $repo->changeCredentials($command);
        $this->repoRepository->save($repo);
    }
}
