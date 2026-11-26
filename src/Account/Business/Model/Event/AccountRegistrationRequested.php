<?php

declare(strict_types=1);

namespace App\Account\Business\Model\Event;

/**
 * Пользователь самостоятельно запросил регистрацию.
 *
 * Аккаунт создаётся в статусе ожидания активации: войти он может, но до
 * решения администратора ему доступна только информационная страница.
 */
final class AccountRegistrationRequested
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role,
        public readonly string $locale = 'ru',
        public readonly string $nickname = '',
    ) {
    }
}
