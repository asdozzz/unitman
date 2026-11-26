<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Имя секрета не прошло валидацию `^[A-Z0-9_]+$`.
 *
 * Такая проверка исключает path traversal в файловом бэкенде.
 */
final class InvalidSecretName extends \DomainException
{
    public static function fromName(string $name): self
    {
        return new self(sprintf(
            'Secret name "%s" is invalid: expected uppercase letters, digits and underscores only (^[A-Z0-9_]+$)',
            $name,
        ));
    }
}
