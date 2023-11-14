<?php

namespace App\Unitman\Acl;

use App\Account\Api\AccountApi;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class AccountAdapter implements UnitmanSecurityService
{
    public function __construct(private AccountApi $accountApi)
    {
    }

    public function isAdmin(): bool
    {
        return $this->accountApi->isAdmin();
    }

    public function getCurrentUserId(): string
    {
        return $this->accountApi->getCurrentUserId();
    }
}
