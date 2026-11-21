<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\LichniyToken;

use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;

interface LichniyTokenListRepository
{
    /**
     * @throws \DomainException lichniy_token.ne_nayden
     */
    public function getById(string $id): LichniyTokenList;

    public function findById(string $id): ?LichniyTokenList;

    /**
     * @return LichniyTokenList[]
     */
    public function findByAccountId(string $accountId): array;

    /**
     * @return LichniyTokenList[]
     */
    public function findByAccountIdAndRepoId(string $accountId, string $repoId): array;

    /**
     * @return LichniyTokenList[]
     */
    public function findAll(): array;
}
