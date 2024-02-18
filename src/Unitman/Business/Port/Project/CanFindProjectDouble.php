<?php

namespace App\Unitman\Business\Port\Project;

interface CanFindProjectDouble
{
    public function isExistDouble(string $projectCode, string $projectName): bool;
    public function isExistDoubleByName(string $projectName): bool;
}
