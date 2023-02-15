<?php

declare(strict_types=1);

namespace App\Account;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use Doctrine\DBAL\Connection;
use Ecotone\Dbal\DbalConnection;
use Enqueue\Dbal\DbalConnectionFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Account\\', './{Business,Infra,Acl}')
        ->exclude(['./{di.php, routing.php, Tests}','./Business/Command','./Business/Model'])
        ->public();

    $services->set(CanGeneateGuid::class, RamseyGuidGenerator::class);

    $services->set(DbalConnectionFactory::class)
        ->factory([DbalConnection::class, 'create'])
        ->args([service(Connection::class)]);
};
