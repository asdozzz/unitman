<?php

declare(strict_types=1);

namespace App\Runner;

use App\Runner\Acl\UnitmanAdapter;
use App\Runner\Business\Port\CanGenerateGuid;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Infra\Activity\UstanovitResultatRabotosposobnostiRunneraActivity;
use App\Runner\Infra\Adapter\RamseyGuidGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$minFreeMemoryPercent','%env(float:MIN_FREE_MEMORY_PERCENT_FOR_RUN_UNIT)%')
        ->bind('$roadRunnerRpcAddress','%env(RR_RPC)%');


    $services->load('App\\Runner\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php,routing.php,Migrations,Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();

    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class);
    $services->set(UmeetObnovlyatStatistikuPoUnitam::class, UnitmanAdapter::class);

    $services->set(UstanovitResultatRabotosposobnostiRunneraActivity::class)
        ->tag('temporal.activity.registry');
};
