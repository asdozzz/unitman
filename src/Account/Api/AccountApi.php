<?php

namespace App\Account\Api;

use App\Account\Infra\Adapter\SymfonySecurityService;
use App\Account\Infra\Repository\JWTUserRepository;

final class AccountApi
{
    public function __construct(private SymfonySecurityService $securityService, private JWTUserRepository $userRepository)
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

    public function getEmailByUserId(string $id): string
    {
        $user = $this->userRepository->getById($id);
        return $user->getEmail();
    }
}
