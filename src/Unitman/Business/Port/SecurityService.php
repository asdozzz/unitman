<?php

namespace App\Unitman\Business\Port;

interface SecurityService
{
    public function isAdmin(): bool;
    public function getCurrentUserId(): string;
}
