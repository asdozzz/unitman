<?php

declare(strict_types=1);

namespace App\App;

use App\App\Infra\EventStore\AuthorMessageDecorator;
use App\App\Infra\Workflow\WorkflowClientFactory;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ObjectMapperPayloadSerializer;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\UuidEncoding\StringUuidEncoder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Temporal\Client\WorkflowClient;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\App\\', './{Business,Infra,Acl}')
        ->exclude(['./{di.php, Tests}','./Business/Command','./Business/Model'])
        ->public();

    $services->set(ObjectMapperPayloadSerializer::class, ObjectMapperPayloadSerializer::class);

    $services->set(ConstructingMessageSerializer::class, ConstructingMessageSerializer::class)
        ->arg('$payloadSerializer', service(ObjectMapperPayloadSerializer::class));

    $services->set(DefaultHeadersDecorator::class, DefaultHeadersDecorator::class);

    $services->set('app.message_decorator', MessageDecoratorChain::class)
        ->args([service(DefaultHeadersDecorator::class), service(AuthorMessageDecorator::class)]);

    $services->set(DefaultTableSchema::class, DefaultTableSchema::class);
    $services->set(StringUuidEncoder::class, StringUuidEncoder::class);

    $services->set(WorkflowClient::class)
        ->factory(service(WorkflowClientFactory::class))
        ->args(['%env(TEMPORAL_CLI_ADDRESS)%']);
};
