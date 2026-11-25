<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;

/**
 * Фейковый узкий порт реквизитов AI-чата.
 *
 * Позволяет задать список реквизитов, сами реквизиты, политики провайдеров и
 * принудительные ошибки политики — без `AccountApi` и БД.
 */
final class FakeRekvizityDlyaSessiiAiChata implements RekvizityDlyaSessiiAiChata
{
    /**
     * @var list<string>
     */
    public array $credentialIds = [];

    /**
     * @var array<string, OpenCodeCredentialSecretReadModel>
     */
    public array $credentials = [];

    /**
     * @var array<string, ProviderSessionPolicyReadModel>
     */
    public array $policies = [];

    /**
     * @var array<string, string>
     */
    public array $policyErrors = [];

    public function __construct(public string $accountId = 'account-1')
    {
    }

    public function tekuschiiAccountId(): string
    {
        return $this->accountId;
    }

    public function identifikatoryRekvizitov(string $accountId): array
    {
        return $this->credentialIds;
    }

    public function poluchitRekvizity(
        string $accountId,
        string $credentialId,
        ?string $model,
    ): OpenCodeCredentialSecretReadModel {
        if (!isset($this->credentials[$credentialId])) {
            throw new \DomainException('account.opencode_credential.not_found');
        }

        $credential = $this->credentials[$credentialId];
        $model = $model !== null ? trim($model) : '';

        if ($model === '') {
            return $credential;
        }

        if (!\in_array($model, $credential->models, true)) {
            throw new \DomainException('account.opencode_credential.model_not_available');
        }

        return new OpenCodeCredentialSecretReadModel(
            $credential->id,
            $credential->accountId,
            $credential->provider,
            $credential->url,
            $credential->models,
            $credential->defaultModel,
            $model,
            $credential->apiKey,
        );
    }

    public function poluchitPolitiku(string $provider): ProviderSessionPolicyReadModel
    {
        if (isset($this->policyErrors[$provider])) {
            throw new \DomainException($this->policyErrors[$provider]);
        }

        if (!isset($this->policies[$provider])) {
            throw new \DomainException('account.opencode_credential.unknown_provider');
        }

        return $this->policies[$provider];
    }
}
