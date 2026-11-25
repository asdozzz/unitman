<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Adapter;

use App\Account\Api\AccountApi;
use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;

/**
 * Acl-адаптер порта реквизитов AI-чата над `AccountApi`.
 *
 * Никакой логики, кроме делегирования: все проверки владения, разрешение
 * модели и политики провайдера остаются внутри модуля Account.
 */
final class AccountRekvizityDlyaSessiiAiChata implements RekvizityDlyaSessiiAiChata
{
    public function __construct(private readonly AccountApi $accountApi)
    {
    }

    public function tekuschiiAccountId(): string
    {
        return $this->accountApi->getCurrentUserId();
    }

    public function identifikatoryRekvizitov(string $accountId): array
    {
        return $this->accountApi->poluchitIdentifikatorySushchestvuyushchihRekvizitov($accountId);
    }

    public function poluchitRekvizity(
        string $accountId,
        string $credentialId,
        ?string $model,
    ): OpenCodeCredentialSecretReadModel {
        return $this->accountApi->poluchitRekvizityOpenCodeDlyVladelca($accountId, $credentialId, $model);
    }

    public function poluchitPolitiku(string $provider): ProviderSessionPolicyReadModel
    {
        return $this->accountApi->poluchitPolitikuSessiiProvaydera($provider);
    }
}
