<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\Account\Api\AccountApi;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\ReadModel\RekvizityOpenCodeModeliReadModel;

/**
 * Антикоррупционный слой над модулем Account.
 *
 * Через него OpenCode узнаёт текущего пользователя, проверяет, что реквизиты
 * принадлежат ему, и разрешает модель хода. API-ключ здесь не запрашивается
 * намеренно: расшифровка происходит внутри Runner, когда тот собирает команду
 * активити.
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

    public function poluchitModeli(string $accountId, string $credentialId): RekvizityOpenCodeModeliReadModel
    {
        $models = $this->accountApi->poluchitModeliRekvizitovOpenCodeDlyVladelca($accountId, $credentialId);

        return new RekvizityOpenCodeModeliReadModel($models->defaultModel, $models->models);
    }

    public function razreshitModel(string $accountId, string $credentialId, ?string $preferred): string
    {
        $models = $this->poluchitModeli($accountId, $credentialId);
        $preferred = $preferred !== null ? trim($preferred) : '';

        if ($preferred === '') {
            return $models->defaultModel;
        }

        if (!\in_array($preferred, $models->models, true)) {
            throw new \DomainException('account.opencode_credential.model_not_available');
        }

        return $preferred;
    }
}
