<?php

declare(strict_types=1);

namespace App\App;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\App\\', './{Business,Infra,Acl}')
        ->exclude(['./{di.php, Tests}','./Business/Command','./Business/Model'])
        ->public();
};
