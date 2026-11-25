<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\Account\Business\Model\OpenCode\ProviderSessionTransport;

/**
 * Политика provider-сессии в виде, пригодном для передачи в другие модули
 * через `AccountApi` (в частности, в AI-чат).
 */
final class ProviderSessionPolicyReadModel
{
    public function __construct(
        public readonly string $provider,
        public readonly string $transport,
        public readonly ?string $key,
        public readonly int $ttlSeconds,
        public readonly bool $supportsAiChat,
    ) {
    }

    public static function izPolitiki(string $provider, ProviderSessionPolicy $policy): self
    {
        return new self(
            $provider,
            $policy->transport->value,
            $policy->key,
            $policy->ttlSeconds,
            $policy->supportsAiChat,
        );
    }

    /**
     * Доменная политика для передачи в транспорт провайдерской сессии.
     *
     * Read-model нужен другим модулям для чтения, а доменная политика — для
     * выбора applier'а. Обратное преобразование остаётся внутри Account,
     * чтобы AiChat не собирал политику вручную.
     */
    public function vPolitiku(): ProviderSessionPolicy
    {
        return new ProviderSessionPolicy(
            ProviderSessionTransport::from($this->transport),
            $this->key,
            $this->ttlSeconds,
            $this->supportsAiChat,
        );
    }
}
