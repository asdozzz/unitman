<?php

namespace App\Unitman\Infra\Repository\Repo;

use App\Unitman\Business\Model\Repo;
use App\Utils\EventSauce\ProjectionsManager;

final class SqlRepoEvensRepository implements \App\Unitman\Business\Port\Repo\RepoRepository
{
    public function __construct(
        private ProjectionsManager $projectionsManager
    )
    {}
    public function getById(string $repoId): Repo
    {
        $repo = $this->projectionsManager->retrieve(Repo::class, Repo\RepoId::fromString($repoId));
        /** @var Repo $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('repo.not_found');
        }
        return $repo;
    }

    public function save(Repo $repo): void
    {
        $this->projectionsManager->persistAndPullProjections($repo);
    }
}
