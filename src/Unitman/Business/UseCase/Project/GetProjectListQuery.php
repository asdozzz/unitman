<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Port\CanGetProjectList;

final class GetProjectListQuery
{
    public function __construct(private readonly CanGetProjectList $repoList)
    {
    }

    function handle(GetProjectList $query): array
    {
        return $this->repoList->getList($query);
    }
}
