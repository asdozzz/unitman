<?php

declare(strict_types=1);

namespace App\Runner\Acl;

use App\Account\Api\AccountApi;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;

/**
 * Антикоррупционный слой между Runner и Account: единственная точка, где
 * Runner узнаёт про роли пользователя.
 */
final class SecurityAdapter implements UmeetProveryatPravaAdmina
{
    public function __construct(private AccountApi $accountApi)
    {
    }

    public function estAdministrator(): bool
    {
        return $this->accountApi->isAdmin();
    }
}
