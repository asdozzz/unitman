<?php

namespace App\Account\Business\Model\Event;

final class AccountWasBlockedByAdmin
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?string $reason
    )
    {
    }
}
