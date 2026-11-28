<?php

declare(strict_types=1);

namespace App\Skill\Tests\Support;

use App\Skill\Business\Port\PravaNavykov;

/**
 * Управляемая реализация прав для UseCase-тестов.
 */
final class FakePravaNavykov implements PravaNavykov
{
    /** @var array<string, 'ADMIN'|'USER'> */
    private array $roli = [];

    public function __construct(
        private string $accountId = 'account-1',
        private bool $admin = false,
    ) {
    }

    public function setAccountId(string $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }

    /**
     * @param 'ADMIN'|'USER' $rol
     */
    public function setRol(string $projectId, string $rol): void
    {
        $this->roli[$projectId] = $rol;
    }

    public function udalitRol(string $projectId): void
    {
        unset($this->roli[$projectId]);
    }

    public function tekuschiiAccountId(): string
    {
        return $this->accountId;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function estAdministratoromVsehProektov(array $projectIds, string $accountId): bool
    {
        if ($projectIds === []) {
            return false;
        }

        foreach ($projectIds as $projectId) {
            if (($this->roli[$projectId] ?? null) !== 'ADMIN') {
                return false;
            }
        }

        return true;
    }

    public function estChlenomHotyaByOdnogoProekta(array $projectIds, string $accountId): bool
    {
        foreach ($projectIds as $projectId) {
            if (isset($this->roli[$projectId])) {
                return true;
            }
        }

        return false;
    }
}
