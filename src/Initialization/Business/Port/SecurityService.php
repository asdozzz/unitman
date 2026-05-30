<?php

namespace App\Initialization\Business\Port;

interface SecurityService
{
    public function isAdmin(): bool;

    public function getCurrentUserId(): string;
}
