<?php

declare(strict_types=1);

namespace App\Account\Business\Model\Event;

/**
 * Аккаунт создан при первом входе через Google/GitHub.
 *
 * Email уже подтверждён провайдером, но аккаунт остаётся в статусе ожидания
 * активации администратором.
 */
final class AccountWasRegisteredViaOAuth
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $email,
        public readonly string $password,
        public readonly string $locale = 'ru',
        public readonly string $nickname = '',
    ) {
    }
}
