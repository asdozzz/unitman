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
        ->bind('$defaultLocale','%env(DEFAULT_LOCALE)%')
        ->bind('$roadRunnerRpcAddress','%env(RR_RPC)%')
    ;

    $services->load('App\\Utils\\', './')
        ->exclude(['./{di.php,di_test.php,routing.php,Tests}', './Model/', './EventSauce/', './Exception/'])
        ->public();

    $services->set(Serializer::class, Serializer::class)
        ->factory(service(SerializerFactory::class));

    // Декоратор объявлен атрибутом #[AsDecorator] на самом классе — здесь задаём только аргументы,
    // потому что ContainerInterface не автовайрится.
    $services->set(MigrationFactoryDecorator::class)
        ->args([service(MigrationFactoryDecorator::class . '.inner'), service('service_container')]);
};
