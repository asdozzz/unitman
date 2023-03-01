<?php

namespace App\Unitman\Business\UseCase;

use App\Unitman\Business\Command\AddRepo;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\CanFindDouble;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\RepoRepository;
use App\Unitman\Business\Port\SecurityService;

final class AddRepoUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private RepoRepository $repoRepository,
        private CanGeneateGuid $uuidGenerator,
        private CanFindDouble $canFindDouble
    )
    {
    }

    function handle(AddRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repoId = $this->uuidGenerator->makeGuid();

        if ($this->canFindDouble->isExistDoubleByEmail($command->repoUrl)) {
            throw new \RuntimeException('repo.url.double');
        }

        $repo = Repo::addRepo($repoId, $command);
        $this->repoRepository->save($repo);
    }
}
