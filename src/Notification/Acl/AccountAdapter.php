<?php

declare(strict_types=1);

namespace App\Notification\Acl;

use App\Account\Api\AccountApi;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\Port\UmeetPoluchatAdministratorov;

/**
 * Антикоррупционный слой между Notification и Account.
 */
final class AccountAdapter implements UmeetOpredelyatPolzovatelya, UmeetPoluchatAdministratorov
{
    public function __construct(private readonly AccountApi $accountApi)
    {
    }

    public function tekushiyAccountId(): string
    {
        return $this->accountApi->getCurrentUserId();
    }

    public function isAdmin(): bool
    {
        return $this->accountApi->isAdmin();
    }

    public function poluchitIdAdministratorov(): array
    {
        return $this->accountApi->poluchitIdAdministratorov();
    }
}
