<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Реквизиты OpenCode для показа пользователю.
 *
 * Содержит только маску ключа — полное значение наружу не отдаётся.
 */
final class OpenCodeCredentialReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $model,
        public readonly string $apiKeyHint,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {
    }
}
