<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

interface CanFindUnitDouble
{
    function isExistsDoubleByName(string $projectId, string $unitName): bool;

    /**
     * @return SpisokUnitovReadModel[]
     * */
    function naitiDubliPoVetke(string $projectId, string $branch): array;
}
