<?php

namespace App\Account\Business\ReadModel;

final class AccountForManaging
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly bool $isBlocked,
        public readonly string $roles,
        public readonly string $password,
        public readonly ?string $nickname = null
    )
    {
    }
}
