<?php

namespace App\Unitman\Business\Port\Unit;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

interface CanGetUnitReadModelById
{
    function getById(string $unitId): SpisokUnitovReadModel;
}
