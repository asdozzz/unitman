<?php

declare(strict_types=1);

namespace App\Skill\Acl;

use App\Account\Api\AccountApi;
use App\Skill\Business\Port\PravaNavykov;
use App\Unitman\Api\UnitmanApi;

/**
 * Антикоррупционный слой авторизации навыков.
 *
 * Права берутся из публичных Api модулей Account и Unitman; внутренние
 * агрегаты и репозитории не используются.
 */
final class PravaNavykovAdapter implements PravaNavykov
{
    public function __construct(
        private readonly AccountApi $accountApi,
        private readonly UnitmanApi $unitmanApi,
    ) {
    }

    public function tekuschiiAccountId(): string
    {
        return $this->accountApi->getCurrentUserId();
    }

    public function isAdmin(): bool
    {
        return $this->accountApi->isAdmin();
    }

    public function estAdministratoromVsehProektov(array $projectIds, string $accountId): bool
    {
        if ($projectIds === []) {
            return false;
        }

        foreach ($projectIds as $projectId) {
            if ($this->unitmanApi->poluchitRolVProekte($projectId, $accountId) !== 'ADMIN') {
                return false;
            }
        }

        return true;
    }

    public function estChlenomHotyaByOdnogoProekta(array $projectIds, string $accountId): bool
    {
        foreach ($projectIds as $projectId) {
            if ($this->unitmanApi->poluchitRolVProekte($projectId, $accountId) !== null) {
                return true;
            }
        }

        return false;
    }
}
