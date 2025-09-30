<?php

namespace App\Account\Business\Utils;

use App\Account\Business\Model\Account\AccountId;
use App\Account\Business\Model\Event\LocaleChanged;
use App\Account\Business\Model\Event\RoleWasChangedByAdmin;

enum AccountEventTypeEnum: string
{
    case Account = 'Account';
    case AccountId = 'AccountId';

    case AccountWasRegistered = 'AccountWasRegistered';

    case SystemAccountWasRegistered = 'SystemAccountWasRegistered';
    case AccountWasBlockedByAdmin = 'AccountWasBlockedByAdmin';
    case AccountWasUnblockedByAdmin = 'AccountWasUnblockedByAdmin';
    case EmailWasChangedByAdmin = 'EmailWasChangedByAdmin';
    case NicknameWasChangedByAdmin = 'NicknameWasChangedByAdmin';
    case RoleWasChangedByAdmin = 'RoleWasChangedByAdmin';
    case PasswordWasChangedByAdmin = 'PasswordWasChangedByAdmin';
    case PasswordWasChanged = 'PasswordWasChanged';
    case NicknameWasChanged = 'NicknameWasChanged';
    case LocaleChanged = 'LocaleChanged';
}
