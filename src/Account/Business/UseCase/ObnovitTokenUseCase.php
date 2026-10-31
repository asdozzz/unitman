<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ObnovitToken;
use App\Account\Business\Model\Account\RefreshToken;
use App\Account\Business\Port\CanGenerateRefreshToken;
use App\Account\Business\Port\CanIssueAccessToken;
use App\Account\Business\Port\RefreshTokenRepository;
use App\Account\Business\ReadModel\TokenPairReadModel;

final class ObnovitTokenUseCase
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private CanGenerateRefreshToken $canGenerateRefreshToken,
        private CanIssueAccessToken $canIssueAccessToken,
        private int $refreshTokenTtl
    )
    {
    }

    public function handle(ObnovitToken $command): TokenPairReadModel
    {
        if ($command->refreshToken === '') {
            throw new \DomainException('account.refresh_token.empty');
        }

        $tokenHash = $this->canGenerateRefreshToken->hash($command->refreshToken);
        $storedToken = $this->refreshTokenRepository->findByHash($tokenHash);

        if ($storedToken === null) {
            throw new \DomainException('account.refresh_token.not_found');
        }

        $storedToken->assertCanBeUsed(time());

        // Проверяем, что аккаунт существует и не заблокирован, до ротации токена.
        $accessToken = $this->canIssueAccessToken->issue($storedToken->getAccountId());

        $storedToken->revoke();
        $this->refreshTokenRepository->revoke($storedToken);

        $newValue = $this->canGenerateRefreshToken->generate();
        $newToken = RefreshToken::issue(
            $this->canGenerateRefreshToken->hash($newValue),
            $storedToken->getAccountId(),
            time() + $this->refreshTokenTtl
        );
        $this->refreshTokenRepository->save($newToken);

        return new TokenPairReadModel($accessToken, $newValue);
    }
}
