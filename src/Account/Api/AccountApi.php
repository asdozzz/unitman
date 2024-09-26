<?php

namespace App\Account\Api;

use App\Account\Business\Model\JWTUser;
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

    public function getCurrentUser(): JWTUser
    {
        $id = $this->securityService->getCurrentUserId();
        $user = $this->userRepository->getById($id);
        return $user;
    }

    public function getUserById(string $userId): JWTUser
    {
        $user = $this->userRepository->getById($userId);
        return $user;
    }

    public function getSystemUser(): JWTUser
    {
        return $this->userRepository->getSystemAccount();
    }
}
