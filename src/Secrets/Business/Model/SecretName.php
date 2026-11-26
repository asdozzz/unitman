<?php

declare(strict_types=1);

namespace App\Secrets\Business\Model;

use App\Secrets\Business\Exception\InvalidSecretName;

/**
 * Имя секрета: только `^[A-Z0-9_]+$`.
 *
 * Строгий набор символов исключает path traversal (`../`, `/`) в файловом
 * бэкенде и не даёт подставить URL-фрагмент в удалённый бэкенд.
 */
final class SecretName
{
    private const PATTERN = '/^[A-Z0-9_]+$/';

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $name): self
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw InvalidSecretName::fromName($name);
        }

        return new self($name);
    }

    public function value(): string
    {
        return $this->value;
    }
}
