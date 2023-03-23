<?php

namespace App\Account\Api;

use App\Account\Infra\Adapter\SymfonySecurityService;

final class AccountApi
{
    public function __construct(private SymfonySecurityService $securityService)
    {
    }

    public function isAdmin(): bool
    {
        return $this->securityService->isAdmin();
    }

    public function getCurrentUserId(): string
    {
        return $this->securityService->getCurrentUserId();
    }
}
