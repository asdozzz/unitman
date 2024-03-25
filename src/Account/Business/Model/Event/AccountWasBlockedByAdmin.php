<?php

namespace App\Account\Business\Model\Event;

use App\Account\Business\Utils\AccountDomainEvent;
use App\Account\Business\Utils\AccountEventTypeEnum;

final class AccountWasBlockedByAdmin implements AccountDomainEvent
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?string $reason
    )
    {
    }

    public static function getEventType(): AccountEventTypeEnum
    {
        return AccountEventTypeEnum::AccountWasBlockedByAdmin;
    }
}
