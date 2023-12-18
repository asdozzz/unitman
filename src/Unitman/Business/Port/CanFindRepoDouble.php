<?php

namespace App\Unitman\Business\Port;

interface CanFindRepoDouble
{
    public function isExistDoubleByName(string $repoName): bool;
}
