<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Бэкенд выбран, но не сконфигурирован (пустой SECRETS_DIR/VAULT_ADDR/KMS_KEY_ID).
 *
 * Fail-closed: любая операция падает с этой ошибкой, сетевых вызовов нет.
 */
final class SecretProviderNotConfigured extends \DomainException
{
    public static function forBackend(string $backend, string $reason): self
    {
        return new self(sprintf('Secret provider "%s" is not configured: %s', $backend, $reason));
    }
}
