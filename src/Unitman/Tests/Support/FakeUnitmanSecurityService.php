<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Port\UnitmanSecurityService;

/**
 * Сервис безопасности с заданным текущим пользователем.
 */
final class FakeUnitmanSecurityService implements UnitmanSecurityService
{
    public function __construct(
        private string $currentUserId = 'user-1',
        private bool $admin = true
    ) {
    }

    public function ustanovitPolzovatelya(string $userId, bool $admin = true): void
    {
        $this->currentUserId = $userId;
        $this->admin = $admin;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function getCurrentUserId(): string
    {
        return $this->currentUserId;
    }

    public function getEmailOrNicknameByUserId(string $id): string
    {
        return $id . '@test.ru';
    }

    public function getUserById(string $userId): Account
    {
        return new Account($userId, $userId . '@test.ru', false);
    }

    public function getSystemUser(): Account
    {
        return new Account('system', 'system@test.ru', true);
    }
}
