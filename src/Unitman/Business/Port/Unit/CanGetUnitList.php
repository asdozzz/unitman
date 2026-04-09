<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

interface CanGetUnitList
{
    function getList(GetUnitList $query, string $currentUserId, array $projectIds): array;
}
