<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Реквизиты OpenCode с РАСШИФРОВАННЫМ ключом.
 *
 * Используется только внутри бэкенда: модуль Account отдаёт их через
 * `AccountApi` модулю Runner, который подставляет их в команду активити.
 * Наружу (в HTTP-ответы) этот объект не выходит.
 *
 * `model` — разрешённая модель текущего хода, `defaultModel` — дефолт реквизитов.
 */
final class OpenCodeCredentialSecretReadModel
{
    /**
     * @param list<string> $models
     */
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $provider,
        public readonly string $url,
        public readonly array $models,
        public readonly string $defaultModel,
        public readonly string $model,
        public readonly string $apiKey,
    ) {
    }
}
