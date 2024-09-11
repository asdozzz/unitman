<?php

declare(strict_types=1);

namespace App\Utils;

use App\Utils\Service\MigrationFactoryDecorator;
use App\Utils\Service\SerializerFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Serializer;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$projectDir','%kernel.project_dir%')
    ;

    $services->load('App\\Utils\\', './')
        ->exclude(['./{di.php, routing.php, Tests}','./Model/','./EventSauce'])
        ->public();



    $services->set(Serializer::class, Serializer::class)
        ->factory(service(SerializerFactory::class));

    $services->set(MigrationFactoryDecorator::class)
        ->decorate('doctrine.migrations.migrations_factory')
        ->args([service('.inner'), service('service_container')]);
};
