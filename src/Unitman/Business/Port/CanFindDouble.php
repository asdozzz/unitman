<?php

namespace App\Unitman\Business\Port;

interface CanFindDouble
{
    public function isExistDoubleByEmail(string $repoUrl): bool;
}
