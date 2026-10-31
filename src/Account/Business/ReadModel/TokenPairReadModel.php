<?php

namespace App\Account\Business\ReadModel;

final class TokenPairReadModel
{
    public function __construct(
        public readonly string $token,
        public readonly string $refreshToken,
    )
    {
    }
}
