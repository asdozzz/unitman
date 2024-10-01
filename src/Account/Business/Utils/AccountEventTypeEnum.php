<?php

namespace App\Account\Business\Utils;

use App\Account\Business\Model\Account\AccountId;
use App\Account\Business\Model\Event\LocaleChanged;

enum AccountEventTypeEnum: string
{
    case Account = 'Account';
    case AccountId = 'AccountId';

    case AccountWasRegistered = 'AccountWasRegistered';

    case SystemAccountWasRegistered = 'SystemAccountWasRegistered';
    case AccountWasBlockedByAdmin = 'AccountWasBlockedByAdmin';
    case AccountWasUnblockedByAdmin = 'AccountWasUnblockedByAdmin';
    case EmailWasChangedByAdmin = 'EmailWasChangedByAdmin';
    case PasswordWasChangedByAdmin = 'PasswordWasChangedByAdmin';
    case LocaleChanged = 'LocaleChanged';
}
