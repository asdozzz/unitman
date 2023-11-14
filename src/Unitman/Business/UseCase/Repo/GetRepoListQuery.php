<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\Port\CanGetRepoList;

final class GetRepoListQuery
{
    public function __construct(private readonly CanGetRepoList $repoList)
    {
    }

    function handle(GetRepoList $query): array
    {
        return $this->repoList->getList($query);
    }
}
