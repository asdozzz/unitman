<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Factory;

use App\Secrets\Business\Port\SecretProvider;
use App\Secrets\Infra\Adapter\EnvSecretProvider;
use App\Secrets\Infra\Adapter\FileSecretProvider;
use App\Secrets\Infra\Adapter\KmsSecretProvider;
use App\Secrets\Infra\Adapter\VaultSecretProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Выбор бэкенда по `SECRET_PROVIDER`.
 *
 * Значения: `env` (по умолчанию), `file`, `vault`, `kms`. Любое другое
 * значение — ошибка конфигурации: контейнер не должен запускаться с
 * неизвестным бэкендом.
 */
final class SecretProviderFactory
{
    public static function create(
        string $backend,
        string $secretsDir = '',
        string $vaultAddress = '',
        string $vaultToken = '',
        string $kmsKeyId = '',
        ?HttpClientInterface $httpClient = null,
    ): SecretProvider {
        $normalized = strtolower(trim($backend));

        return match ($normalized) {
            'env' => new EnvSecretProvider(),
            'file' => new FileSecretProvider($secretsDir),
            'vault' => new VaultSecretProvider($vaultAddress, $vaultToken, $httpClient),
            'kms' => new KmsSecretProvider($kmsKeyId),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown SECRET_PROVIDER "%s"; allowed values: env, file, vault, kms',
                $backend,
            )),
        };
    }
}
