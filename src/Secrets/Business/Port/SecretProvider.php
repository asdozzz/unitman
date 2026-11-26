<?php

declare(strict_types=1);

namespace App\Secrets\Business\Port;

/**
 * Абстракция доступа к секретам (env/file/vault/kms).
 *
 * Контракт:
 * - `get()` возвращает непустое значение либо бросает доменное исключение;
 *   значение никогда не попадает в сообщение об ошибке.
 * - `has()` не бросает исключение для отсутствующего секрета, но обязан
 *   падать при небезопасной конфигурации бэкенда (например, файл 0644).
 * - `rotate()` либо выполняет ротацию, либо бросает
 *   `UnsupportedSecretOperation`; успех никогда не имитируется.
 */
interface SecretProvider
{
    /**
     * @throws \App\Secrets\Business\Exception\SecretNotFound
     * @throws \App\Secrets\Business\Exception\SecretProviderNotConfigured
     */
    public function get(string $name): string;

    public function has(string $name): bool;

    /**
     * @throws \App\Secrets\Business\Exception\UnsupportedSecretOperation
     */
    public function rotate(string $name): void;
}
