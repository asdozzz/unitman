<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

/**
 * Проверка прав текущего пользователя. Реализуется Acl-слоем поверх Account.
 */
interface UmeetProveryatPravaAdmina
{
    public function estAdministrator(): bool;
}
