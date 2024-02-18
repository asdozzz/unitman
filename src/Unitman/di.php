<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Projection\ProjectMessageDispatcherFactory;
use App\Unitman\Infra\Projection\RepoMessageDispatcherFactory;
use App\Unitman\Infra\Projection\UnitMessageDispatcherFactory;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;
use App\Unitman\Infra\Repository\Repo\SqlRepoEvensRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\IdEncoding\StringIdEncoder;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineMessageRepository;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Unitman\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php, routing.php, Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();

    $services->set(CanGeneateGuid::class, RamseyGuidGenerator::class);

    $services->set('unitman.message_repository', DoctrineMessageRepository::class)
        ->args([
            service(Connection::class),
            'unitman_event_store',
            service(ConstructingMessageSerializer::class),
            0,
            service(DefaultTableSchema::class),
            service(StringIdEncoder::class)
        ]);

    $services->set('repo.message_dispatcher', SynchronousMessageDispatcher::class)
        ->factory([service(RepoMessageDispatcherFactory::class), 'getMessageDispatcher']);

    $services->set(SqlRepoEvensRepository::class)
        ->args([
            service(Connection::class),
            service('unitman.message_repository'),
            service('repo.message_dispatcher'),
            service('app.message_decorator'),
        ]);

    $services->set('project.message_dispatcher', SynchronousMessageDispatcher::class)
        ->factory([service(ProjectMessageDispatcherFactory::class), 'getMessageDispatcher']);

    $services->set(SqlProjectEventsRepository::class)
        ->args([
            service(Connection::class),
            service('unitman.message_repository'),
            service('project.message_dispatcher'),
            service('app.message_decorator'),
        ]);

    $services->set('unit.message_dispatcher', SynchronousMessageDispatcher::class)
        ->factory([service(UnitMessageDispatcherFactory::class), 'getMessageDispatcher']);

    $services->set(SqlUnitEventsRepository::class)
        ->args([
            service(Connection::class),
            service('unitman.message_repository'),
            service('unit.message_dispatcher'),
            service('app.message_decorator'),
        ]);
};
