<?php

namespace App\Account\Business\Model\Event;

class SystemAccountWasRegistered
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role
    )
    {
    }
}
