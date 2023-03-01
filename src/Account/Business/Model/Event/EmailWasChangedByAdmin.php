<?php

namespace App\Account\Business\Model\Event;

final class EmailWasChangedByAdmin
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newEmail
    )
    {
    }

}
