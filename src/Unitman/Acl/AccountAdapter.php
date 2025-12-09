<?php

namespace App\Unitman\Acl;

use App\Account\Api\AccountApi;
use App\Unitman\Business\Model\Account;
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

    public function getCurrentUser(): Account
    {
        $userFromAccountContext = $this->accountApi->getCurrentUser();

        return new Account($userFromAccountContext->getId(), $userFromAccountContext->getEmail(), $userFromAccountContext->hasSystemRole());
    }

    public function getUserById(string $userId): Account
    {
        $userFromAccountContext = $this->accountApi->getUserById($userId);
        return new Account($userFromAccountContext->getId(), $userFromAccountContext->getEmail(), $userFromAccountContext->hasSystemRole());
    }

    public function getEmailOrNicknameByUserId(string $id): string
    {
        return $this->accountApi->getEmailOrNicknameByUserId($id);
    }

    public function getSystemUser(): Account
    {
        $user = $this->accountApi->getSystemUser();

        return new Account($user->getId(), $user->getEmail(), true);
    }
}
