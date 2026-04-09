<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\ReadModel\ProjectList;

interface CanGetProjectList
{
    function getList(GetProjectList $query): array;

    /**
     * @return ProjectList[]
     * */
    function getListByRepoId(string $repoId): array;
}
