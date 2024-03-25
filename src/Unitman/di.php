<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Account\Business\Model\Account;
use App\App\Infra\EventStore\AuthorMessageDecorator;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Utils\ClassNameMapFactory;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Projection\ProjectMessageDispatcherFactory;
use App\Unitman\Infra\Projection\RepoMessageDispatcherFactory;
use App\Unitman\Infra\Projection\UnitMessageDispatcherFactory;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;
use App\Unitman\Infra\Repository\Repo\SqlRepoEvensRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use App\Utils\EventSauce\ProjectionsManager;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\ExplicitlyMappedClassNameInflector;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializerSupportingObjectMapperAndSerializablePayload;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\IdEncoding\StringIdEncoder;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineMessageRepository;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Unitman\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php, routing.php, Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();

    $services->set(CanGeneateGuid::class, RamseyGuidGenerator::class);

    $services->set('unitman.event_type_mapper', ExplicitlyMappedClassNameInflector::class)
        ->factory([service(ClassNameMapFactory::class), 'getMap']);

    $services->set('unitman.event_serializer', ConstructingMessageSerializer::class)
        ->args([
            service('unitman.event_type_mapper'),
            service(PayloadSerializerSupportingObjectMapperAndSerializablePayload::class)
        ]);

    $services->set('unitman.message_repository', DoctrineMessageRepository::class)
        ->args([
            service(Connection::class),
            'unitman_event_store',
            service('unitman.event_serializer'),
            0,
            service(DefaultTableSchema::class),
            service(StringIdEncoder::class)
        ]);

    $services->set('unitman.stream_repository', DoctrineStreamRepository::class)
        ->args([
            service(Connection::class),
            'unitman_event_store',
            service('unitman.event_serializer'),
        ]);

    $services->set('unitman.default_decorator', DefaultHeadersDecorator::class)
        ->args([service('unitman.event_type_mapper')]);

    $services->set('unitman.message_decorator', MessageDecoratorChain::class)
        ->args([service('unitman.default_decorator'), service(AuthorMessageDecorator::class)]);

    $services->set('unitman.projections_manager', ProjectionsManager::class)
        ->arg('$projections', tagged_iterator('unitman.projection'))
        ->arg('$checkpointStore', service('app.checkpoint_store'))
        ->arg('$eventsRepository', service('unitman.stream_repository'));

    $services->set('repo.event_store', EventSourcedAggregateRootRepository::class)
        ->args([
            Repo::class,
            service('unitman.message_repository'),
            null,
            service('unitman.message_decorator'),
            service('unitman.event_type_mapper'),
        ]);

    $services->set(SqlRepoEvensRepository::class)
        ->args([
            service(Connection::class),
            service('repo.event_store'),
            service('unitman.projections_manager')
        ]);

    $services->set('project.event_store', EventSourcedAggregateRootRepository::class)
        ->args([
            Project::class,
            service('unitman.message_repository'),
            null,
            service('unitman.message_decorator'),
            service('unitman.event_type_mapper'),
        ]);

    $services->set(SqlProjectEventsRepository::class)
        ->args([
            service(Connection::class),
            service('project.event_store'),
            service('unitman.projections_manager'),
        ]);

    $services->set('unit.event_store', EventSourcedAggregateRootRepository::class)
        ->args([
            Unit::class,
            service('unitman.message_repository'),
            null,
            service('unitman.message_decorator'),
            service('unitman.event_type_mapper'),
        ]);

    $services->set(SqlUnitEventsRepository::class)
        ->args([
            service(Connection::class),
            service('unit.event_store'),
            service('unitman.projections_manager'),
        ]);
};
