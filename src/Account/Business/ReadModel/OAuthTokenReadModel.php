<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

final class OAuthTokenReadModel
{
    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     roles: list<string>,
     *     locale: string,
     *     nickname: ?string,
     *     activationPending: bool,
     *     emailVerified: bool
     * } $user
     */
    public function __construct(
        public readonly string $token,
        public readonly string $refreshToken,
        public readonly array $user,
    ) {
    }
}
