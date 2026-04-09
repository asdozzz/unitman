<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\ReadModel\ProjectList;

interface CanGetActiveProjectList
{
    /**
     * @return ProjectList[]
     * */
    function getActiveList(GetActiveProjectList $query): array;
}
