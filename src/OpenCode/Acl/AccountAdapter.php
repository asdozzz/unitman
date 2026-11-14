<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\Account\Api\AccountApi;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;

/**
 * Антикоррупционный слой над модулем Account.
 *
 * Через него OpenCode узнаёт текущего пользователя и проверяет, что реквизиты
 * принадлежат ему. API-ключ здесь не запрашивается намеренно: расшифровка
 * происходит внутри Runner, когда тот собирает команду активити.
 */
final class AccountAdapter implements UmeetProveryatRekvizityOpenCode
{
    public function __construct(private AccountApi $accountApi)
    {
    }

    public function tekuschiiAccountId(): string
    {
        return $this->accountApi->getCurrentUserId();
    }

    public function trebovatVladelca(string $accountId, string $credentialId): void
    {
        $this->accountApi->trebovatRekvizityOpenCodeVladelca($accountId, $credentialId);
    }
}