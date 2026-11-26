<?php

declare(strict_types=1);

namespace App\Account\Business\Model\Event;

/**
 * Администратор активировал самостоятельно зарегистрированный аккаунт.
 *
 * Вместе со снятием флага ожидания аккаунт получает полноценную роль
 * пользователя.
 */
final class AccountWasActivatedByAdmin
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newRole,
    ) {
    }
}
