<?php

namespace App\Unitman\Acl;

use App\Account\Api\AccountApi;
use App\Unitman\Business\Port\SecurityService;

final class AccountAdapter implements SecurityService
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
