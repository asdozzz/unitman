<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Port\Project\CanGetProjectList;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        // Fail closed: админский read должен возвращать 403, а не 500.
        if (!$this->securityService->isAdmin()) {
            throw new AccessDeniedHttpException('security.access_denied');
        }

        return $this->repoList->getList($query);
    }
}
