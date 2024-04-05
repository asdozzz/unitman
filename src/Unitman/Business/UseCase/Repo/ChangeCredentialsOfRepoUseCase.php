<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Repo\UmeetPoluchatUrlHranilisha;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ChangeCredentialsOfRepoUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository         $repoRepository,
        private UmeetPoluchatUrlHranilisha $umeetPoluchatUrlHranilisha
    )
    {
    }

    function handle(ChangeCredentialsOfRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repo = $this->repoRepository->getById($command->repoId);

        $url = $this->umeetPoluchatUrlHranilisha->poluchitUrlHranilisha($repo->getType(), $command->repoUrl ?? $repo->getCredentials()->url);

        $repo->changeCredentials($command, $url);
        $this->repoRepository->save($repo);
    }
}
