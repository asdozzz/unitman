<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

/**
 * Генератор разового пароля для аккаунта, созданного администратором.
 */
interface CanGenerateTemporaryPassword
{
    public function makeTemporaryPassword(): string;
}
