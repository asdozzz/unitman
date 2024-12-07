<?php

namespace App\Account\Business\Model\Event;

class AccountWasRegistered
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role,
        public readonly string $locale = 'ru',
        public readonly string $nickname = '',
    )
    {
    }
}
