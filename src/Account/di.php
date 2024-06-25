<?php

declare(strict_types=1);

namespace App\Account;

use App\Account\Business\Model\Account;
use App\Account\Business\Port\UuidGenerator;
use App\Account\Business\Utils\EventTypeMapFactory;
use App\Account\Infra\Adapter\RamseyUuidGenerator;
use App\Account\Infra\Repository\SqlAccountRepository;
use App\App\Infra\EventStore\AuthorMessageDecorator;
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
use EventSauce\UuidEncoding\StringUuidEncoder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$appEnv','%env(APP_ENV)%');

    $services->load('App\\Account\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php, routing.php, Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();

    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);

    $services->set('account.event_type_mapper', ExplicitlyMappedClassNameInflector::class)
        ->factory([service(EventTypeMapFactory::class), 'getMap']);

    $services->set('account.event_serializer', ConstructingMessageSerializer::class)
        ->args([
            service('account.event_type_mapper'),
            service(PayloadSerializerSupportingObjectMapperAndSerializablePayload::class)
        ]);

    $services->set('account.message_repository', DoctrineMessageRepository::class)
        ->args([
            service(Connection::class),
            'account_event_store',
            service('account.event_serializer'),
            0,
            service(DefaultTableSchema::class),
            service(StringIdEncoder::class)
        ]);

    $services->set('account.default_decorator', DefaultHeadersDecorator::class)
        ->args([service('account.event_type_mapper')]);

    $services->set('account.message_decorator', MessageDecoratorChain::class)
        ->args([service('account.default_decorator'), service(AuthorMessageDecorator::class)]);

    $services->set('account.stream_repository', DoctrineStreamRepository::class)
        ->args([
            service(Connection::class),
            'account_event_store',
            service('account.event_serializer'),
            service('account.event_type_mapper'),
            service('account.message_decorator'),
            service('account.message_repository'),
        ]);

    $services->set('account.projections_manager', ProjectionsManager::class)
        ->arg('$projections', tagged_iterator('account.sync_projection'))
        ->arg('$checkpointStore', service('app.checkpoint_store'))
        ->arg('$eventsRepository', service('account.stream_repository'));

    $services->set('account.event_store', EventSourcedAggregateRootRepository::class)
        ->args([
            Account::class,
            service('account.message_repository'),
            null,
            service('account.message_decorator'),
            service('account.event_type_mapper'),
        ]);

    $services->set(SqlAccountRepository::class)
        ->args([
            service('account.projections_manager')
        ]);
};
