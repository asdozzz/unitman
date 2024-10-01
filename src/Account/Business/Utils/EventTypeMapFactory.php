<?php

namespace App\Account\Business\Utils;

use App\Account\Business\Model\Account;
use App\Account\Business\Model\Account\AccountId;
use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\LocaleChanged;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use App\Account\Business\Model\Event\SystemAccountWasRegistered;
use EventSauce\EventSourcing\ExplicitlyMappedClassNameInflector;

final class EventTypeMapFactory
{
    function getMap(): ExplicitlyMappedClassNameInflector
    {
        $classToEventTypeMap = [
            Account::class => AccountEventTypeEnum::Account,
            AccountId::class => AccountEventTypeEnum::AccountId,
            AccountWasBlockedByAdmin::class => AccountEventTypeEnum::AccountWasBlockedByAdmin,
            AccountWasRegistered::class => AccountEventTypeEnum::AccountWasRegistered,
            SystemAccountWasRegistered::class => AccountEventTypeEnum::SystemAccountWasRegistered,
            AccountWasUnblockedByAdmin::class => AccountEventTypeEnum::AccountWasUnblockedByAdmin,
            EmailWasChangedByAdmin::class => AccountEventTypeEnum::EmailWasChangedByAdmin,
            PasswordWasChangedByAdmin::class => AccountEventTypeEnum::PasswordWasChangedByAdmin,
            LocaleChanged::class => AccountEventTypeEnum::LocaleChanged,
        ];

        $map = [];

        foreach ($classToEventTypeMap as $key => $enum) {
            $map[$key] = $enum->value;
        }

        return new ExplicitlyMappedClassNameInflector($map);
    }
}
