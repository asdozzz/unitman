<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Projection\RepoMessageDispatcherFactory;
use App\Unitman\Infra\Repository\SqlRepoRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineUuidV4MessageRepository;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\UuidEncoding\StringUuidEncoder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Unitman\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php, routing.php, Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
        ->public();

    $services->set(CanGeneateGuid::class, RamseyGuidGenerator::class);

    $services->set('repo.message_repository', DoctrineUuidV4MessageRepository::class)
        ->args([
            service(Connection::class),
            'unitman_event_store',
            service(ConstructingMessageSerializer::class),
            0,
            service(DefaultTableSchema::class),
            service(StringUuidEncoder::class)
        ]);

    $services->set('repo.message_dispatcher', SynchronousMessageDispatcher::class)
        ->factory([service(RepoMessageDispatcherFactory::class), 'getMessageDispatcher']);

    $services->set(SqlRepoRepository::class)
        ->args([
            service(Connection::class),
            service('repo.message_repository'),
            service('repo.message_dispatcher'),
            service('app.message_decorator'),
        ]);
};
