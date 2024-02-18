<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

interface CanGetUnitList
{
    /**
     * @return SpisokUnitovReadModel[]
     * */
    function getList(GetUnitList $query): array;
}
