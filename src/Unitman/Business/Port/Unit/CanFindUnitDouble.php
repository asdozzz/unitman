<?php

namespace App\Unitman\Business\Port\Unit;

interface CanFindUnitDouble
{
    function isExistsDoubleByName(string $projectId, string $unitName): bool;
}
