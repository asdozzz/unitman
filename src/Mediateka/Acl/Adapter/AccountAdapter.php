<?php

declare(strict_types=1);

namespace App\Mediateka\Acl\Adapter;

use App\Account\Api\AccountApi;
use App\Mediateka\Business\Port\CurrentUserProvider;

/**
 * Антикоррупционный адаптер к модулю Account.
 *
 * Идентификатор текущего пользователя берётся из публичного API Account,
 * чтобы домен Mediateka не зависел от чужого модуля напрямую. Если пользователь
 * не аутентифицирован, AccountApi бросает исключение — оно пробрасывается наружу.
 */
final class AccountAdapter implements CurrentUserProvider
{
    public function __construct(private readonly AccountApi $accountApi)
    {
    }

    public function getCurrentUserId(): string
    {
        return $this->accountApi->getCurrentUserId();
    }
}
