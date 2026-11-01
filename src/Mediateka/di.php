<?php

declare(strict_types=1);

namespace App\Mediateka;

use App\Mediateka\Acl\Adapter\AccountAdapter;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\Port\CurrentUserProvider;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Infra\Adapter\MinioFileStorage;
use App\Mediateka\Infra\Adapter\RamseyGuidGenerator;
use App\Mediateka\Infra\Repository\SqlFaylUnitaRepository;
use App\Mediateka\Infra\S3\S3Settings;
use App\Mediateka\Infra\S3\SigV4Signer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$maxFileSize', '%env(int:MEDIATEKA_MAX_FILE_SIZE)%');

    $services->load('App\\Mediateka\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php, di_test.php, routing.php, Migrations, Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
        ])
        ->public();

    $services->set(S3Settings::class)
        ->arg('$endpoint', '%env(MINIO_ENDPOINT)%')
        ->arg('$publicEndpoint', '%env(MINIO_PUBLIC_ENDPOINT)%')
        ->arg('$bucket', '%env(MEDIATEKA_BUCKET)%')
        ->arg('$region', '%env(MINIO_REGION)%')
        ->arg('$accessKey', '%env(MINIO_ROOT_USER)%')
        ->arg('$secretKey', '%env(MINIO_ROOT_PASSWORD)%')
        ->arg('$usePathStyle', '%env(bool:MINIO_USE_PATH_STYLE)%')
        ->arg('$presignTtl', '%env(int:MEDIATEKA_PRESIGN_TTL)%');

    $services->set(SigV4Signer::class)
        ->arg('$accessKey', '%env(MINIO_ROOT_USER)%')
        ->arg('$secretKey', '%env(MINIO_ROOT_PASSWORD)%')
        ->arg('$region', '%env(MINIO_REGION)%');

    $services->set(FileStorage::class, MinioFileStorage::class);
    $services->set(FaylUnitaRepository::class, SqlFaylUnitaRepository::class);
    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class);
    $services->set(CurrentUserProvider::class, AccountAdapter::class);
};
