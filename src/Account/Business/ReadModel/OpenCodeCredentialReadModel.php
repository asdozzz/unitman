<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Реквизиты OpenCode для показа пользователю.
 *
 * Содержит только маску ключа — полное значение наружу не отдаётся.
 * Поле `model` оставлено синонимом `defaultModel` для обратной совместимости.
 */
final class OpenCodeCredentialReadModel
{
    /**
     * @param list<string> $models
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $provider,
        public readonly string $url,
        public readonly array $models,
        public readonly string $defaultModel,
        public readonly string $model,
        public readonly string $apiKeyHint,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {
    }
}
