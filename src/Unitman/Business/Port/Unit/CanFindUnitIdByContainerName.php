<?php

namespace App\Unitman\Business\Port\Unit;

interface CanFindUnitIdByContainerName
{
    function findIdByNameAndProjectName(string $containerName): ?string;
}
