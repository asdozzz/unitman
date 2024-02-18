<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Port\Project\CanGetProjectList;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class GetProjectListQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private readonly CanGetProjectList $repoList
    )
    {
    }

    function handle(GetProjectList $query): array
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        return $this->repoList->getList($query);
    }
}
