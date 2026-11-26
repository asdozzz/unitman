<?php

namespace App\Account\Business\ReadModel;

final class UserList
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly bool $isBlocked,
        public readonly ?string $nickname = null,
        public readonly bool $pendingActivation = false,
        public readonly bool $emailVerified = true,
    )
    {
    }

}
