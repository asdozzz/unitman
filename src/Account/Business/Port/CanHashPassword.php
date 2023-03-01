<?php

namespace App\Account\Business\Port;

interface CanHashPassword
{
    public function hashPassword(string $password): string;
}
