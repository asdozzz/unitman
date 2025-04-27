<?php

declare(strict_types=1);

namespace App\Unitman;

use App\App\Infra\EventStore\AuthorMessageDecorator;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Utils\ClassNameMapFactory;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\BackgroundJob\StatistikaPoProektu\StatistikaPoProektuBackgroundJob;
use App\Unitman\Infra\BackgroundJob\WebsocketUnitEvent\WebsocketUnitEventJob;
use App\Unitman\Infra\Jobs\OcheredDlyProzesaObnovleniyaPosleZapuskaJobHandler;
use App\Unitman\Infra\Jobs\OcheredDlyProzesaSozdaniyaUnitaSystemoiJobHandler;
use App\Unitman\Infra\Jobs\OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaJobHandler;
use App\Unitman\Infra\Jobs\SobitieIzHranilishaJobsHandler;
use App\Unitman\Infra\Jobs\WebsocketUnitEventReadModelJobHandler;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;
use App\Unitman\Infra\Repository\Repo\SqlRepoEvensRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use App\Unitman\Infra\Service\RebuildService;
use App\Unitman\Infra\Temporal\Activity\ProzesAvtosborkiUnitaSystemoiActivity;
use App\Unitman\Infra\Temporal\Activity\ProzesObnovlenieKodaPosleZapuskaActivity;
use App\Unitman\Infra\BackgroundJob\UnitRunnerJobs\UnitRunnerJobs;
use App\Unitman\Infra\Temporal\Activity\ProzesUdaleniyaUnitaPosleZapuskaActivity;
use App\Utils\EventSauce\ProjectionsManager;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\ExplicitlyMappedClassNameInflector;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializerSupportingObjectMapperAndSerializablePayload;
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
        ->autoconfigure()
        ->bind('$appEnv','%env(APP_ENV)%');

    $services->load('App\\Unitman\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php, routing.php, Migrations, Tests}','./Business/Command','./Business/Model','./Business/ReadModel'])
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
            service('unitman.event_type_mapper'),
            service('unitman.message_decorator'),
            service('unitman.message_repository'),
        ]);

    $services->set('unitman.default_decorator', DefaultHeadersDecorator::class)
        ->args([service('unitman.event_type_mapper')]);

    $services->set('unitman.message_decorator', MessageDecoratorChain::class)
        ->args([service('unitman.default_decorator'), service(AuthorMessageDecorator::class)]);

    $services->set('unitman.projections_manager', ProjectionsManager::class)
        ->arg('$projections', tagged_iterator('unitman.projection'))
        ->arg('$checkpointStore', service('app.checkpoint_store'))
        ->arg('$eventsRepository', service('unitman.stream_repository'));

    $services->set(RebuildService::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(SqlRepoEvensRepository::class)
        ->args([
            service('unitman.projections_manager')
        ]);

    $services->set(SqlProjectEventsRepository::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(SqlUnitEventsRepository::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(UnitRunnerJobs::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(StatistikaPoProektuBackgroundJob::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(WebsocketUnitEventJob::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(Infra\BackgroundJob\ProjectRunnerJobs\ProjectRunnerJobs::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(ProzesObnovlenieKodaPosleZapuskaActivity::class)
        ->tag('temporal.activity.registry');

    $services->set(ProzesUdaleniyaUnitaPosleZapuskaActivity::class)
        ->tag('temporal.activity.registry');

    $services->set(ProzesAvtosborkiUnitaSystemoiActivity::class)
        ->tag('temporal.activity.registry');

    $services->set(OcheredDlyProzesaObnovleniyaPosleZapuskaJobHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaJobHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(OcheredDlyProzesaSozdaniyaUnitaSystemoiJobHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(SobitieIzHranilishaJobsHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(WebsocketUnitEventReadModelJobHandler::class)
        ->tag('roadrunner_jobs.handler');
};
