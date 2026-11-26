<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Adapter;

use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Business\Port\SecretProvider;

/**
 * In-memory провайдер для тестов и для статических фабрик существующих
 * потребителей. Не выбирается через `SECRET_PROVIDER` и не читает env.
 */
final class ArraySecretProvider implements SecretProvider
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values = [])
    {
    }

    public function get(string $name): string
    {
        $value = $this->values[$name] ?? null;

        if (!is_string($value) || $value === '') {
            throw SecretNotFound::named($name, 'array');
        }

        return $value;
    }

    public function has(string $name): bool
    {
        $value = $this->values[$name] ?? null;

        return is_string($value) && $value !== '';
    }

    public function rotate(string $name): void
    {
        throw new UnsupportedSecretOperation('array: in-memory provider is read-only');
    }
}
