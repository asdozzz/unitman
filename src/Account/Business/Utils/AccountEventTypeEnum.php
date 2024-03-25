<?php

namespace App\Account\Business\Utils;

use App\Account\Business\Model\Account\AccountId;

enum AccountEventTypeEnum: string
{
    case Account = 'Account';
    case AccountId = 'AccountId';

    case AccountWasRegistered = 'AccountWasRegistered';
    case AccountWasBlockedByAdmin = 'AccountWasBlockedByAdmin';
    case AccountWasUnblockedByAdmin = 'AccountWasUnblockedByAdmin';
    case EmailWasChangedByAdmin = 'EmailWasChangedByAdmin';
    case PasswordWasChangedByAdmin = 'PasswordWasChangedByAdmin';

}
