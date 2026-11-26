<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Adapter;

use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Business\Port\SecretProvider;

/**
 * Бэкенд по умолчанию: читает переменные окружения по имени.
 *
 * Пустое значение трактуется как отсутствие секрета (silent default
 * запрещён). Ротация выполняется только перезапуском окружения, поэтому
 * `rotate()` честно бросает `UnsupportedSecretOperation`.
 */
final class EnvSecretProvider implements SecretProvider
{
    /**
     * @param array<string, mixed>|null $variables Явный снимок окружения
     *        (для тестов); null означает `$_ENV`/`$_SERVER`/`getenv()`.
     */
    public function __construct(private readonly ?array $variables = null)
    {
    }

    public function get(string $name): string
    {
        $value = $this->read($name);

        if ($value === null || $value === '') {
            throw SecretNotFound::named($name, 'env');
        }

        return $value;
    }

    public function has(string $name): bool
    {
        $value = $this->read($name);

        return $value !== null && $value !== '';
    }

    public function rotate(string $name): void
    {
        throw new UnsupportedSecretOperation(
            'env: environment variables are read-only; rotate the deployment environment and restart',
        );
    }

    private function read(string $name): ?string
    {
        if ($this->variables !== null) {
            if (!array_key_exists($name, $this->variables)) {
                return null;
            }

            $value = $this->variables[$name];

            return is_string($value) ? $value : null;
        }

        foreach ([$_ENV, $_SERVER] as $bag) {
            if (array_key_exists($name, $bag) && is_string($bag[$name])) {
                return $bag[$name];
            }
        }

        $value = getenv($name);

        return is_string($value) ? $value : null;
    }
}
