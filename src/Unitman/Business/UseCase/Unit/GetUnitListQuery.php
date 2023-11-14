<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\CanGetUnitList;

final class GetUnitListQuery
{
    public function __construct(private CanGetUnitList $repo)
    {
    }

    function handle(GetUnitList $query): array
    {
        return $this->repo->getList($query);
    }
}
