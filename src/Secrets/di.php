<?php

declare(strict_types=1);

namespace App\Secrets;

use App\Secrets\Business\Port\EncryptionKeyProvider;
use App\Secrets\Business\Port\SecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;
use App\Secrets\Infra\Factory\SecretProviderFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration): void {
    // Параметры нужны, чтобы отсутствие переменной не роняло сборку контейнера
    // там, где бэкенд выключен; выбранный бэкенд всё равно fail-closed.
    $configuration->parameters()->set('secret_provider', 'env');
    $configuration->parameters()->set('secrets_dir', '');
    $configuration->parameters()->set('vault_addr', '');
    $configuration->parameters()->set('vault_token', '');
    $configuration->parameters()->set('kms_key_id', '');

    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Secrets\\', './{Business,Infra}')
        ->exclude([
            './{di.php,Tests}',
            './Business/Exception',
            './Business/Model',
            './Business/Port',
            // Адаптеры создаются фабрикой с явными аргументами, а не
            // автовайрингом: у file/vault/kms обязательные настройки.
            './Infra/Adapter',
            './Infra/Factory',
        ])
        ->public();

    // Единственный бэкенд на процесс. Неизвестное значение SECRET_PROVIDER
    // падает при создании сервиса — до первого обращения к секрету.
    $services->set(SecretProvider::class)
        ->factory([SecretProviderFactory::class, 'create'])
        ->args([
            '%env(default:secret_provider:SECRET_PROVIDER)%',
            '%env(default:secrets_dir:SECRETS_DIR)%',
            '%env(default:vault_addr:VAULT_ADDR)%',
            '%env(default:vault_token:VAULT_TOKEN)%',
            '%env(default:kms_key_id:KMS_KEY_ID)%',
            service('http_client'),
        ]);

    $services->set(EncryptionKeyProvider::class, SecretEncryptionKeyProvider::class);
};
