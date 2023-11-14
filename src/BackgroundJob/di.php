<?php

declare(strict_types=1);

namespace App\BackgroundJob;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\BackgroundJob\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php,routing.php, Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();
};
