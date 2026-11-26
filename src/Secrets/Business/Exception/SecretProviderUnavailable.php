<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Бэкенд доступен, но операция не удалась (транспорт/HTTP-статус).
 *
 * Сообщение содержит только код состояния, без тела ответа и значений.
 */
final class SecretProviderUnavailable extends \DomainException
{
    public static function forBackend(string $backend, string $reason): self
    {
        return new self(sprintf('Secret provider "%s" is unavailable: %s', $backend, $reason));
    }
}
