<?php

declare(strict_types=1);

namespace App\BackgroundJob;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        // Адрес RPC-сокета RoadRunner: единый источник — переменная RR_RPC,
        // она же подставляется в .rr.yaml (rpc.listen).
        ->bind('$roadRunnerRpcAddress', '%env(RR_RPC)%');

    $services->load('App\\BackgroundJob\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php,routing.php,Migrations,Tests}', './Business/Command', './Business/Model', './Business/ReadModel'])
        ->public();
};
