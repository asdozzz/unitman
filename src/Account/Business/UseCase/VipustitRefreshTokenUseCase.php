<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Model\Account\RefreshToken;
use App\Account\Business\Port\CanGenerateRefreshToken;
use App\Account\Business\Port\RefreshTokenRepository;

final class VipustitRefreshTokenUseCase
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private CanGenerateRefreshToken $canGenerateRefreshToken,
        private int $refreshTokenTtl
    )
    {
    }

    public function handle(string $accountId): string
    {
        $value = $this->canGenerateRefreshToken->generate();

        $refreshToken = RefreshToken::issue(
            $this->canGenerateRefreshToken->hash($value),
            $accountId,
            time() + $this->refreshTokenTtl
        );

        $this->refreshTokenRepository->save($refreshToken);

        return $value;
    }
}
