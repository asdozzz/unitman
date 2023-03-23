<?php

declare(strict_types=1);

namespace App\Account;

use App\Account\Business\Model\Account;
use App\Account\Business\Port\UuidGenerator;
use App\Account\Infra\Adapter\RamseyUuidGenerator;
use App\Account\Infra\Projection\AccountMessageDispatcherFactory;
use App\Account\Infra\Projection\SyncProjectionForAccount;
use App\Account\Infra\Repository\SqlAccountRepository;
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

    $services->load('App\\Account\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php, routing.php, Tests}','./Business/Command','./Business/Model'])
        ->public();

    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);

    $services->set('account.message_repository', DoctrineUuidV4MessageRepository::class)
        ->args([
            service(Connection::class),
            'account_event_store',
            service(ConstructingMessageSerializer::class),
            0,
            service(DefaultTableSchema::class),
            service(StringUuidEncoder::class)
        ]);

    $services->set('account.message_dispatcher', SynchronousMessageDispatcher::class)
        ->factory([service(AccountMessageDispatcherFactory::class), 'getMessageDispatcher']);

    $services->set(SqlAccountRepository::class)
        ->args([
            service(Connection::class),
            service('account.message_repository'),
            service('account.message_dispatcher'),
            service('app.message_decorator'),
        ]);
};
