<?php

namespace App\Account\Business\Port;

interface SecurityService
{
    public function isAdmin(): bool;

    public function getCurrentUserId(): string;
}
