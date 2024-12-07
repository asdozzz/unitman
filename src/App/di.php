<?php

declare(strict_types=1);

namespace App\App;

use App\App\Infra\EventStore\AuthorMessageDecorator;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Utils\EventSauce\Repository\CheckpointStore;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\ExplicitlyMappedClassNameInflector;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ObjectMapperPayloadSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializerSupportingObjectMapperAndSerializablePayload;
use EventSauce\IdEncoding\StringIdEncoder;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use Monolog\Level;
use Symfony\Bridge\Monolog\Handler\ElasticsearchLogstashHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Temporal\Client\WorkflowClient;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\App\\', './{Business,Infra,Acl}')
        ->exclude(['./{di.php, Migrations, Tests}','./Business/Command','./Business/Model'])
        ->public();

    $services->set(PayloadSerializerSupportingObjectMapperAndSerializablePayload::class, PayloadSerializerSupportingObjectMapperAndSerializablePayload::class);

    $services->set(DefaultTableSchema::class, DefaultTableSchema::class);
    $services->set(StringIdEncoder::class, StringIdEncoder::class);

    $services->set('app.checkpoint_store', CheckpointStore::class)
        ->arg('$tableName', 'checkpoint_store');


    $services->set(WorkflowClient::class)
        ->factory(service(WorkflowClientFactory::class))
        ->args(['%env(TEMPORAL_CLI_ADDRESS)%']);

    $services->set(ElasticsearchLogstashHandler::class)
        ->args([
            '$endpoint' => "http://elasticsearch:9200",
            '$index' => "monolog",
        ]);
};
