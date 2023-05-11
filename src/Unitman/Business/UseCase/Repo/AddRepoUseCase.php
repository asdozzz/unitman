<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanFindRepoDouble;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\RepoRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class AddRepoUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository         $repoRepository,
        private CanGeneateGuid         $uuidGenerator,
        private CanFindRepoDouble      $canFindDouble
    )
    {
    }

    function handle(AddRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repoId = $this->uuidGenerator->makeGuid();

        if ($command->repoType !== RepoType::GITHUB->value && $this->canFindDouble->isExistDoubleByUrl($command->repoUrl)) {
            throw new \RuntimeException('repo.url.double');
        }

        $repo = Repo::addRepo($repoId, $command);
        $this->repoRepository->save($repo);
    }
}
