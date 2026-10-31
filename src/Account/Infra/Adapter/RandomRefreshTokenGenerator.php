<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\CanGenerateRefreshToken;

final class RandomRefreshTokenGenerator implements CanGenerateRefreshToken
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $refreshToken): string
    {
        return hash('sha256', $refreshToken);
    }
}
