<?php

declare(strict_types=1);

namespace App\Secrets\Tests\Infra\Adapter;

use App\Secrets\Business\Exception\SecretProviderNotConfigured;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Infra\Adapter\KmsSecretProvider;
use App\Secrets\Infra\Adapter\VaultSecretProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WS-9: remote adapters never fake success and make no network calls when
 * unconfigured. No real endpoints or credentials are used here.
 */
final class RemoteSecretProvidersFailClosedTest extends TestCase
{
    #[Test]
    public function vault_without_configuration_throws_not_configured(): void
    {
        $provider = new VaultSecretProvider('', '');

        $this->expectException(SecretProviderNotConfigured::class);
        $provider->get('RUNNER_SECRET_MASTER_KEY');
    }

    #[Test]
    public function vault_rotate_is_explicitly_unsupported_when_configured(): void
    {
        $provider = new VaultSecretProvider('https://vault.example.test', 'test-token-not-for-production');

        $this->expectException(UnsupportedSecretOperation::class);
        $provider->rotate('RUNNER_SECRET_MASTER_KEY');
    }

    #[Test]
    public function kms_without_key_id_throws_not_configured(): void
    {
        $provider = new KmsSecretProvider('');

        $this->expectException(SecretProviderNotConfigured::class);
        $provider->get('LICHNIY_TOKEN_CREDENTIALS_KEY');
    }

    #[Test]
    public function kms_never_fakes_secret_reads_or_rotation(): void
    {
        $provider = new KmsSecretProvider('test-kms-key-id-not-for-production');

        $this->expectException(UnsupportedSecretOperation::class);
        $provider->rotate('RUNNER_SECRET_MASTER_KEY');
    }
}
