<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\ReadModel\RepoList;

interface CanGetRepoList
{
    /**
     * @return RepoList[]
     * */
    function getList(GetRepoList $query): array;
}
