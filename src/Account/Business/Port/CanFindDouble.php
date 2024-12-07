<?php

namespace App\Account\Business\Port;

interface CanFindDouble
{
    public function isExistDoubleByEmail(string $email): bool;

    public function isExistDoubleByNickname(string $id, string $nickname): bool;


}
