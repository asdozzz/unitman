<?php

namespace App\Unitman\Business\Port;

interface CanFindUnitDouble
{
    function isExistsDoubleByName(string $projectId, string $unitName): bool;
}
