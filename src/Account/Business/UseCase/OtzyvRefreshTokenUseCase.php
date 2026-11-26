<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\CanGenerateRefreshToken;
use App\Account\Business\Port\RefreshTokenRepository;

/**
 * Отзывает refresh-токен при logout.
 *
 * Никогда не бросает исключений и не логирует значение: неизвестный или уже
 * отозванный токен — это не ошибка logout, cookie всё равно очищается.
 */
final class OtzyvRefreshTokenUseCase
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly CanGenerateRefreshToken $canGenerateRefreshToken,
    ) {
    }

    public function handle(string $refreshToken): void
    {
        if ($refreshToken === '') {
            return;
        }

        $storedToken = $this->refreshTokenRepository->findByHash(
            $this->canGenerateRefreshToken->hash($refreshToken),
        );

        if ($storedToken === null || $storedToken->isRevoked()) {
            return;
        }

        $storedToken->revoke();
        $this->refreshTokenRepository->revoke($storedToken);
    }
}
