<?php

namespace App\Unitman\Business\Port\Repo;

use App\Unitman\Business\Command\Repo\GetActiveRepoList;
use App\Unitman\Business\ReadModel\RepoList;

interface CanGetActiveRepoList
{
    /**
     * @return RepoList[]
     * */
    function getActiveRepoList(GetActiveRepoList $query): array;
}
