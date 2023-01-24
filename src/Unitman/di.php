<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Unitman\\', './{Business,Infra,Acl}')
        ->exclude(['./{di.php, routing.php, Tests}','./Business/Command','./Business/Model'])
        ->public();

    $services->set(CanGeneateGuid::class, RamseyGuidGenerator::class);
};
