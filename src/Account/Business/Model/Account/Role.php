<?php

namespace App\Account\Business\Model\Account;

enum Role: string
{
    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN';

    case ROLE_SYSTEM = 'ROLE_SYSTEM';
}
