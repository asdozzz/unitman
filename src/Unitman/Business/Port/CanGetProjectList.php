<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\ReadModel\RepoList;

interface CanGetProjectList
{
    /**
     * @return RepoList[]
     * */
    function getList(GetProjectList $query): array;
}
