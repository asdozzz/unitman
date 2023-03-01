<?php

namespace App\Account\Business\Command;

final class RegisterFirstAccount
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    )
    {
    }

}
