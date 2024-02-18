<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\Port\Repo\CanGetRepoList;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class GetRepoListQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private readonly CanGetRepoList $repoList
    )
    {
    }

    function handle(GetRepoList $query): array
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        return $this->repoList->getList($query);
    }
}
