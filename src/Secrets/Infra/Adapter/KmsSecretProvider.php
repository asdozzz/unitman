<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Adapter;

use App\Secrets\Business\Exception\SecretProviderNotConfigured;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Business\Port\SecretProvider;

/**
 * KMS adapter-stub.
 *
 * Контракт: KMS — сервис управления ключами, а не хранилище произвольных
 * секретов. Поэтому `get`/`has`/`rotate` не реализуются поверх него и
 * честно бросают `UnsupportedSecretOperation`; при пустом `KMS_KEY_ID`
 * сначала бросается `SecretProviderNotConfigured`. Успех никогда не
 * имитируется, сетевых вызовов нет.
 *
 * Если позднее понадобится KMS, здесь появится envelope-шифрование
 * (`Decrypt` для ключей, полученных из файла/env), а не «чтение секрета».
 */
final class KmsSecretProvider implements SecretProvider
{
    public function __construct(private readonly string $keyId)
    {
    }

    public function get(string $name): string
    {
        $this->assertConfigured();

        throw new UnsupportedSecretOperation(
            'kms: reading stored secrets is not supported; use env/file or Vault for secret storage',
        );
    }

    public function has(string $name): bool
    {
        $this->assertConfigured();

        throw new UnsupportedSecretOperation(
            'kms: reading stored secrets is not supported; use env/file or Vault for secret storage',
        );
    }

    public function rotate(string $name): void
    {
        $this->assertConfigured();

        throw new UnsupportedSecretOperation(
            'kms: rotating a stored secret is not supported; rotate the KMS key per the operator runbook',
        );
    }

    private function assertConfigured(): void
    {
        if (trim($this->keyId) === '') {
            throw SecretProviderNotConfigured::forBackend('kms', 'KMS_KEY_ID is empty');
        }
    }
}
