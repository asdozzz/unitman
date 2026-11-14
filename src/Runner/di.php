<?php

declare(strict_types=1);

namespace App\Runner;

use App\Runner\Acl\AccountAdapter;
use App\Runner\Acl\UnitmanAdapter;
use App\Runner\Business\Port\CanGenerateGuid;
use App\Runner\Business\Port\UmeetChitatProgressZadachi;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\Port\UmeetPoluchatRekvizityOpenCode;
use App\Runner\Infra\Activity\UstanovitResultatRabotosposobnostiRunneraActivity;
use App\Runner\Infra\Adapter\RamseyGuidGenerator;
use App\Runner\Infra\Adapter\RunnerProgressKvStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$minFreeMemoryPercent','%env(float:MIN_FREE_MEMORY_PERCENT_FOR_RUN_UNIT)%')
        ->bind('$roadRunnerRpcAddress','%env(RR_RPC)%')
        ->bind('$runnerCallbackSecret','%env(RUNNER_CALLBACK_SECRET)%');


    $services->load('App\\Runner\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php,routing.php,Migrations,Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();

    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class);
    $services->set(UmeetObnovlyatStatistikuPoUnitam::class, UnitmanAdapter::class);
    $services->set(UmeetPoluchatRekvizityOpenCode::class, AccountAdapter::class);

    // Оба порта прогресса указывают на один экземпляр: писатель и читатель
    // обязаны работать с одним и тем же KV-хранилищем.
    $services->set(RunnerProgressKvStorage::class);
    $services->alias(UmeetHranitProgressZadachi::class, RunnerProgressKvStorage::class);
    $services->alias(UmeetChitatProgressZadachi::class, RunnerProgressKvStorage::class);

    $services->set(UstanovitResultatRabotosposobnostiRunneraActivity::class)
        ->tag('temporal.activity.registry');
};
