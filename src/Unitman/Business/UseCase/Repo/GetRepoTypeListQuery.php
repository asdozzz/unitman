<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\GetRepoTypeList;
use App\Unitman\Business\Port\Repo\CanGetRepoTypeList;

final class GetRepoTypeListQuery
{
    public function __construct(private CanGetRepoTypeList $canGetRepoTypeList)
    {
    }

    function handle(GetRepoTypeList $query): array
    {
        return $this->canGetRepoTypeList->getRepoTypeList($query);
    }
}
