<?php

namespace App\Unitman\Business\Port\Repo;

interface CanFindRepoDouble
{
    public function isExistDoubleByName(string $repoName): bool;
}
