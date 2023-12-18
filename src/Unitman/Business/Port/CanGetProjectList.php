<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\ReadModel\ProjectList;

interface CanGetProjectList
{
    /**
     * @return ProjectList[]
     * */
    function getList(GetProjectList $query): array;

    /**
     * @return ProjectList[]
     * */
    function getListByRepoId(string $repoId): array;
}
