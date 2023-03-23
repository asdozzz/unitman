<?php

namespace App\Unitman\Business\Port;

interface CanFindUnitDouble
{
    function isExistsDoubleByName(string $unitName): bool;
}
