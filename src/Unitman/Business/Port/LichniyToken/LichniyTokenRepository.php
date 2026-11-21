<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\LichniyToken;

use App\Unitman\Business\Model\LichniyToken;

interface LichniyTokenRepository
{
    /**
     * @throws \DomainException lichniy_token.ne_nayden
     */
    public function getById(string $id): LichniyToken;

    public function findById(string $id): ?LichniyToken;

    public function save(LichniyToken $token): void;
}
