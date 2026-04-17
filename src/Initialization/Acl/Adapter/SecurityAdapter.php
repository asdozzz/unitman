<?php

namespace App\Initialization\Acl\Adapter;

use App\Initialization\Business\Port\SecurityService as InitializationSecurityService;
use App\Account\Api\AccountApi;

final class SecurityAdapter implements InitializationSecurityService
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
