<?php

namespace App\Unitman\Business\Port\Repo;

use App\Unitman\Business\Command\Repo\GetRepoTypeList;
use App\Unitman\Business\ReadModel\RepoTypeList;

interface CanGetRepoTypeList
{
    /**
     * @return RepoTypeList[]
     * */
    function getRepoTypeList(GetRepoTypeList $query): array;
}
