<?php

namespace App\Account\Business\Model\Event;

final class PasswordWasChangedByAdmin
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newPassword
    )
    {
    }

}
