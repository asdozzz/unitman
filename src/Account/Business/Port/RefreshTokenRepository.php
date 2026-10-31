<?php

namespace App\Account\Business\Port;

use App\Account\Business\Model\Account\RefreshToken;

interface RefreshTokenRepository
{
    public function save(RefreshToken $refreshToken): void;

    public function findByHash(string $tokenHash): ?RefreshToken;

    public function revoke(RefreshToken $refreshToken): void;

    public function revokeAllByAccountId(string $accountId): void;
}
