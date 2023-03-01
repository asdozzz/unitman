<?php

namespace App\Account\Business\Model\Event;

final class AccountWasUnblockedByAdmin
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?string $reason
    )
    {
    }
}
