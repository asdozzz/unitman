<?php

namespace App\Account\Business\Model\Account;

enum Role: string
{
    /**
     * Аккаунт зарегистрировался самостоятельно и ожидает активации
     * администратором. Такой роли достаточно для входа и доступа к
     * информационной странице, но недостаточно для остальных API.
     */
    case ROLE_PENDING = 'ROLE_PENDING';

    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN';

    case ROLE_SYSTEM = 'ROLE_SYSTEM';
}
