<?php

declare(strict_types=1);

namespace App\Runner;

use App\Runner\Acl\AccountAdapter;
use App\Runner\Acl\SecurityAdapter;
use App\Runner\Acl\UnitmanAdapter;
use App\Runner\Business\Port\CanGenerateGuid;
use App\Runner\Business\Port\ProjectBuildStateRepository;
use App\Runner\Business\Port\RunnerSelector;
use App\Runner\Business\Port\UmeetChitatProgressZadachi;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\Port\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\Port\UmeetSozdavatUvedomlenieOBugroze;
use App\Runner\Business\Port\UmeetPoluchatRekvizityOpenCode;
use App\Runner\Business\Port\UmeetPoluchatKredensialyRepozitoriya;
use App\Runner\Business\Port\UmeetPoluchatUrlKlonaProekta;
use App\Runner\Acl\NotificationAdapter;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Infra\Adapter\RamseyGuidGenerator;
use App\Runner\Infra\Adapter\RunnerProgressKvStorage;
use App\Runner\Infra\Repository\SqlProjectBuildStateRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$minFreeMemoryPercent','%env(float:MIN_FREE_MEMORY_PERCENT_FOR_RUN_UNIT)%')
        ->bind('$roadRunnerRpcAddress','%env(RR_RPC)%')
        // Детерминированный секрет раннера берётся из SecretProvider (WS-9):
        // APP_SECRET-fallback удалён, пустой master key — fail-closed.
        // Порты, которые backend отдаёт раннеру при регистрации v2.
        ->bind('$runnerApiPort','%env(default::UNITMAN_API_PORT)%')
        ->bind('$runnerTemporalPort','%env(default::UNITMAN_TEMPORAL_PUBLIC_PORT)%')
        ->bind('$runnerRegistryPort','%env(default::UNITMAN_REGISTRY_PUBLIC_PORT)%')
        ->bind('$registryPublicAddress','%env(default::UNITMAN_REGISTRY_PUBLIC_ADDRESS)%');


    $services->load('App\\Runner\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
            // DTO/исключения регистрации не являются сервисами.
            './Business/UseCase/RegisterRunner',
            // Исключения build-state без зависимостей, в контейнер не нужны.
            './Business/UseCase/ProjectBuildState',
        ])
        ->public();

    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class);
    $services->set(RunnerSelector::class, DefaultRunnerSelector::class);
    $services->set(ProjectBuildStateRepository::class, SqlProjectBuildStateRepository::class);
    $services->set(UmeetObnovlyatStatistikuPoUnitam::class, UnitmanAdapter::class);
    $services->set(UmeetObnovlyatSostoyanieKonteyneraUnita::class, UnitmanAdapter::class);
    $services->set(UmeetPoluchatUrlKlonaProekta::class, UnitmanAdapter::class);
    $services->set(UmeetPoluchatKredensialyRepozitoriya::class, UnitmanAdapter::class);
    $services->set(UmeetPoluchatRekvizityOpenCode::class, AccountAdapter::class);
    $services->set(UmeetProveryatPravaAdmina::class, SecurityAdapter::class);
    $services->set(UmeetSozdavatUvedomlenieOBugroze::class, NotificationAdapter::class);

    // Оба порта прогресса указывают на один экземпляр: писатель и читатель
    // обязаны работать с одним и тем же KV-хранилищем.
    $services->set(RunnerProgressKvStorage::class);
    $services->alias(UmeetHranitProgressZadachi::class, RunnerProgressKvStorage::class);
    $services->alias(UmeetChitatProgressZadachi::class, RunnerProgressKvStorage::class);
};
