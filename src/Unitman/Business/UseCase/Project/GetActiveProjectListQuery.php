<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Port\Project\CanGetActiveProjectList;
use App\Unitman\Business\Port\Project\CanGetProjectList;

final class GetActiveProjectListQuery
{
    public function __construct(private readonly CanGetActiveProjectList $projectList)
    {
    }

    function handle(GetActiveProjectList $query): array
    {
        return $this->projectList->getActiveList($query);
    }
}
