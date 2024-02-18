<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\GetActiveRepoList;
use App\Unitman\Business\Port\Repo\CanGetActiveRepoList;

final class GetActiveRepoListQuery
{
    public function __construct(private CanGetActiveRepoList $canGetRepoTypeList)
    {
    }

    function handle(GetActiveRepoList $query): array
    {
        return $this->canGetRepoTypeList->getActiveRepoList($query);
    }
}
