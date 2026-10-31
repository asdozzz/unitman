<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\CanIssueAccessToken;
use App\Account\Infra\Repository\JWTUserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class LexikAccessTokenIssuer implements CanIssueAccessToken
{
    public function __construct(
        private JWTTokenManagerInterface $tokenManager,
        private JWTUserRepository $userRepository
    )
    {
    }

    public function issue(string $accountId): string
    {
        // Аккаунт проверяется здесь же: для заблокированного пользователя токен не выпускается.
        $user = $this->userRepository->getActiveById($accountId);

        $token = $this->createToken($user);

        if (is_string($token)) {
            return $token;
        }

        if (is_array($token) && isset($token['token']) && is_string($token['token'])) {
            return $token['token'];
        }

        throw new \RuntimeException('account.access_token.invalid_payload');
    }

    /**
     * Менеджер токенов исторически возвращает либо строку, либо массив с ключом token,
     * поэтому тип намеренно расширен до mixed.
     */
    private function createToken(JWTUser $user): mixed
    {
        return $this->tokenManager->create($user);
    }
}
