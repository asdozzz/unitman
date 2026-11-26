<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Данные пользователя, полученные от OAuth-провайдера.
 */
final class OAuthUserReadModel
{
    public function __construct(
        public readonly string $providerUserId,
        public readonly string $email,
        public readonly ?string $nickname = null,
    ) {
    }
}
