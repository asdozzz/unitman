<?php

namespace App\Utils\Exception;

enum TranslatorKeyword: string
{
    case SYSTEM_CANNOT_ADD_USERS = 'account.as_system_not_allowed';
    case ACCOUNT_NOT_AUTHENTICATED = 'account.not_authenticated';

    case UNIT_NAME_ALREADY_EXISTS = 'unit.name_already_exists';
}
