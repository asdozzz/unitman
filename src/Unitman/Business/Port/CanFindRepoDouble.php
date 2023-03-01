<?php

namespace App\Unitman\Business\Port;

interface CanFindRepoDouble
{
    public function isExistDoubleByUrl(string $repoUrl): bool;
}
