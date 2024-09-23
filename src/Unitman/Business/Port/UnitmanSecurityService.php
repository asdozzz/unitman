<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Account;

interface UnitmanSecurityService
{
    public function isAdmin(): bool;
    public function getCurrentUserId(): string;

    public function getEmailByUserId(string $id): string;

    public function getUserById(string $userId): Account;
}
