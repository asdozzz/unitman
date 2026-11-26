<?php

declare(strict_types=1);

namespace App\Secrets\Tests\Infra\Factory;

use App\Secrets\Business\Exception\SecretProviderNotConfigured;
use App\Secrets\Infra\Adapter\EnvSecretProvider;
use App\Secrets\Infra\Adapter\FileSecretProvider;
use App\Secrets\Infra\Adapter\KmsSecretProvider;
use App\Secrets\Infra\Adapter\VaultSecretProvider;
use App\Secrets\Infra\Factory\SecretProviderFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WS-9: backend selection is explicit and fails closed for unknown values.
 */
final class SecretProviderFactoryTest extends TestCase
{
    #[Test]
    public function env_is_the_default_backend(): void
    {
        self::assertInstanceOf(EnvSecretProvider::class, SecretProviderFactory::create('env'));
    }

    #[Test]
    public function selects_file_backend(): void
    {
        self::assertInstanceOf(
            FileSecretProvider::class,
            SecretProviderFactory::create('file', sys_get_temp_dir()),
        );
    }

    #[Test]
    public function selects_vault_and_kms_adapters(): void
    {
        self::assertInstanceOf(VaultSecretProvider::class, SecretProviderFactory::create('vault', '', 'https://vault.test', 'token'));
        self::assertInstanceOf(KmsSecretProvider::class, SecretProviderFactory::create('kms', '', '', '', 'key-id'));
    }

    #[Test]
    public function unknown_backend_is_a_configuration_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SECRET_PROVIDER');

        SecretProviderFactory::create('not-a-backend');
    }

    #[Test]
    public function file_backend_without_directory_fails_closed(): void
    {
        $this->expectException(SecretProviderNotConfigured::class);

        SecretProviderFactory::create('file', '');
    }

    #[Test]
    public function unconfigured_vault_fails_closed_on_every_operation(): void
    {
        $provider = SecretProviderFactory::create('vault', '', '', '');

        $this->expectException(SecretProviderNotConfigured::class);
        $provider->get('RUNNER_SECRET_MASTER_KEY');
    }

    #[Test]
    public function unconfigured_kms_fails_closed_on_every_operation(): void
    {
        $provider = SecretProviderFactory::create('kms');

        $this->expectException(SecretProviderNotConfigured::class);
        $provider->rotate('LICHNIY_TOKEN_CREDENTIALS_KEY');
    }
}
