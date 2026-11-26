<?php

namespace App\Account\Business\ReadModel;

final class AccountForManaging
{
    /**
     * Пароль/хеш пароля намеренно отсутствует: административный список не
     * должен отдавать учётные данные (LT-4, WS-11).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly bool $isBlocked,
        public readonly string $roles,
        public readonly ?string $nickname = null,
        public readonly bool $pendingActivation = false,
        public readonly bool $emailVerified = true,
    )
    {
    }
}
