<?php

namespace App\Unitman\Business\Port;

interface UnitmanSecurityService
{
    public function isAdmin(): bool;
    public function getCurrentUserId(): string;
}
