<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Запрошенный секрет отсутствует в настроенном бэкенде.
 *
 * Сообщение содержит только имя переменной/файла, но никогда — значение.
 */
final class SecretNotFound extends \DomainException
{
    public static function named(string $name, string $backend): self
    {
        return new self(sprintf('Secret "%s" is not configured (backend: %s)', $name, $backend));
    }
}
