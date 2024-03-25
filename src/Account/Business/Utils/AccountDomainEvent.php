<?php

namespace App\Account\Business\Utils;

interface AccountDomainEvent
{
    public static function getEventType(): AccountEventTypeEnum;
}
