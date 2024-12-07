<?php

namespace App\Account\Business\Model\Event;

final class PasswordWasChanged
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newPassword
    )
    {
    }

}
