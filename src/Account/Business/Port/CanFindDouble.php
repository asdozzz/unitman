<?php

namespace App\Account\Business\Port;

interface CanFindDouble
{
    public function isExistDoubleByEmail(string $email): bool;
}
