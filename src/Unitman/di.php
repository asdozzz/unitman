<?php

declare(strict_types=1);

namespace App\Unitman;

use App\App\Infra\EventStore\AuthorMessageDecorator;
use App\Mediateka\Business\Port\FileLinkChecker;
use App\Unitman\Acl\MediatekaAdapter;
use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenRepository;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\UmeetPoluchatSostoyanieVetki;
use App\Unitman\Business\Port\Project\UmeetPoluchatTokenDlyaProekta;
use App\Unitman\Business\Port\Project\UmeetProveryatPushTokenom;
use App\Unitman\Business\Port\Project\UmeetSozdavatVetkuProekta;
use App\Unitman\Business\Port\Redaktor\UmeetPodpisivatSsylkuRedaktora;
use App\Unitman\Business\Port\Repo\UmeetProveryatTokenHranilisha;
use App\Unitman\Business\Port\RunnerPublicAddressProvider;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\Unit\UmeetPoluchatTokenRedaktirovaniya;
use App\Unitman\Business\Port\Unit\UmeetPoluchatUnitiDlyaOprosaVetok;
use App\Unitman\Business\Port\Project\CanGetProjectRunnerJobs;
use App\Unitman\Business\Port\Queue\ZadachaVOchered;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\UnitRunnerRepository;
use App\Unitman\Business\Port\Unit\UmeetSozdavatUnit;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatStatistikuKonteinera;
use App\Unitman\Business\Port\Unit\UmeetOpoveshatObIzmeneniiUnita;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;
use App\Unitman\Business\Utils\ClassNameMapFactory;
use App\Unitman\Infra\Adapter\HmacRedaktorLinkSigner;
use App\Unitman\Infra\Adapter\RunnerRepositoryPublicAddressProvider;
use App\Unitman\Infra\Adapter\PersonalTokenOpenSslShifr;
use App\Unitman\Infra\Adapter\PersonalTokenResolver;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Adapter\StorageApiAdapterFactory;
use App\Unitman\Infra\Adapter\StorageApiAdapter\WorkspaceProjectStorageAdapter;
use App\Unitman\Infra\Adapter\ZadachaVOcheredAdapter;
use App\Unitman\Infra\Repository\LichniyToken\SqlLichniyTokenListRepository;
use App\Unitman\Infra\Repository\LichniyToken\SqlLichniyTokenRepository;
use App\Unitman\Infra\Repository\Project\ProjectRunnerJobRepository;
use App\Unitman\Infra\Security\UnitVariableCipher;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SqlOtlozhennieDeistviyaRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitRunnerRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitiDlyaOprosaVetokRepository;
use App\Unitman\Infra\Repository\Unit\SqlZanyatostUnitaRepository;
use App\Unitman\Infra\Repository\VolumeType\SqlVolumeTypeFileRepository;
use App\Unitman\Infra\Repository\VolumeType\SqlVolumeTypeRepository;
use App\Unitman\Infra\Service\VolumeTypeFileLinkChecker;
use App\Unitman\Infra\Service\WebsocketUnitEventNotifier;
use App\Unitman\Infra\BackgroundJob\StatistikaPoProektu\StatistikaPoProektuBackgroundJob;
use App\Unitman\Infra\BackgroundJob\WebhookEvent\WebhookEventProjectionPuller;
use App\Unitman\Infra\BackgroundJob\WebsocketUnitEvent\WebsocketUnitEventJob;
use App\Unitman\Infra\BackgroundJob\WebsocketProjectEvent\WebsocketProjectEventJob;
use App\Unitman\Infra\Jobs\SobitieIzHranilishaJobsHandler;
use App\Unitman\Infra\Jobs\WebhookEventJobHandler;
use App\Unitman\Infra\Jobs\WebsocketProjectEventReadModelJobHandler;
use App\Unitman\Infra\Jobs\WebsocketUnitEventReadModelJobHandler;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;
use App\Unitman\Infra\Repository\Repo\SqlRepoEvensRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use App\Unitman\Infra\Service\RebuildService;
use App\Unitman\Infra\BackgroundJob\UnitRunnerJobs\UnitRunnerJobs;
use App\Utils\EventSauce\ProjectionsManager;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
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
        ->bind('$appEnv','%env(APP_ENV)%')
        ->bind('$roadRunnerRpcAddress','%env(RR_RPC)%');

    $services->load('App\\Unitman\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
            // Не сервисы: подключаются явно либо используются миграцией.
            './Infra/EventSauce',
            './Infra/Security',
        ])
        ->public();

    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class);
    // Infra/Security исключён из автолоада: сервис регистрируется явно.
    $services->set(UnitVariableCipher::class);
    $services->set(MediatekaService::class, MediatekaAdapter::class);
    $services->set(VolumeTypeRepository::class, SqlVolumeTypeRepository::class);
    $services->set(VolumeTypeFileRepository::class, SqlVolumeTypeFileRepository::class);
    $services->set(ZadachaVOchered::class, ZadachaVOcheredAdapter::class);
    $services->set(UmeetObnovlyatStatistikuKonteinera::class, SpisokUnitovRepository::class);
    $services->set(UmeetObnovlyatSostoyanieKonteyneraUnita::class, SpisokUnitovRepository::class);
    $services->set(UmeetOpoveshatObIzmeneniiUnita::class, WebsocketUnitEventNotifier::class);
    $services->set(
        \App\Unitman\Business\Port\Unit\UmeetSozdavatUvedomlenieOZapuskeUnita::class,
        \App\Unitman\Acl\NotificationAdapter::class,
    );
    $services->set(ZanyatostUnitaRepository::class, SqlZanyatostUnitaRepository::class);
    $services->set(OtlozhennieDeistviyaRepository::class, SqlOtlozhennieDeistviyaRepository::class);
    $services->set(CanGetProjectRunnerJobs::class, ProjectRunnerJobRepository::class);
    $services->set(UmeetSozdavatUnit::class, \App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase::class);
    $services->set(UnitRunnerRepository::class, SqlUnitRunnerRepository::class);
    $services->set(RunnerPublicAddressProvider::class, RunnerRepositoryPublicAddressProvider::class);

    $services->set(VolumeTypeFileLinkChecker::class)
        ->tag(FileLinkChecker::TAG);

    $services->set('unitman.event_type_mapper', ExplicitlyMappedClassNameInflector::class)
        ->factory([service(ClassNameMapFactory::class), 'getMap']);

    $services->set('unitman.base_event_serializer', ConstructingMessageSerializer::class)
        ->args([
            service('unitman.event_type_mapper'),
            service(PayloadSerializerSupportingObjectMapperAndSerializablePayload::class)
        ]);

    // Legacy-формат событий репозитория удалён: БД создаётся с нуля, а события
    // сразу содержат только `token_cipher`/`token_hint`. Upcaster не нужен.
    $services->alias('unitman.event_serializer', 'unitman.base_event_serializer');

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

    $services->set(WebsocketProjectEventJob::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(WebhookEventProjectionPuller::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(Infra\BackgroundJob\ProjectRunnerJobs\ProjectRunnerJobs::class)
        ->args([
            service('unitman.projections_manager'),
        ]);

    $services->set(SobitieIzHranilishaJobsHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(WebsocketUnitEventReadModelJobHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(WebsocketProjectEventReadModelJobHandler::class)
        ->tag('roadrunner_jobs.handler');

    $services->set(WebhookEventJobHandler::class)
        ->tag('roadrunner_jobs.handler');

    // ─── Личные токены хранилищ ──────────────────────────────────────────────
    $services->set(UmeetShifrovatSekrety::class, PersonalTokenOpenSslShifr::class);
    $services->alias(UmeetProveryatPushTokenom::class, WorkspaceProjectStorageAdapter::class);
    $services->alias(UmeetProveryatTokenHranilisha::class, WorkspaceProjectStorageAdapter::class);
    $services->alias(UmeetPoluchatTokenRedaktirovaniya::class, PersonalTokenResolver::class);
    $services->alias(UmeetPoluchatTokenDlyaProekta::class, PersonalTokenResolver::class);
    $services->alias(UmeetSozdavatVetkuProekta::class, StorageApiAdapterFactory::class);
    $services->alias(UmeetPoluchatSostoyanieVetki::class, StorageApiAdapterFactory::class);
    $services->set(UmeetPoluchatUnitiDlyaOprosaVetok::class, SqlUnitiDlyaOprosaVetokRepository::class);
    $services->alias(UmeetPodpisivatSsylkuRedaktora::class, HmacRedaktorLinkSigner::class);

    $services->set('unitman.lichniy_token.event_serializer', ConstructingMessageSerializer::class)
        ->args([
            service('unitman.event_type_mapper'),
            service(PayloadSerializerSupportingObjectMapperAndSerializablePayload::class),
        ]);

    $services->set('unitman.lichniy_token.message_repository', DoctrineMessageRepository::class)
        ->args([
            service(Connection::class),
            'unitman_lichniy_token_event_store',
            service('unitman.lichniy_token.event_serializer'),
            0,
            service(DefaultTableSchema::class),
            service(StringIdEncoder::class),
        ]);

    $services->set('unitman.lichniy_token.default_decorator', DefaultHeadersDecorator::class)
        ->args([service('unitman.event_type_mapper')]);

    $services->set('unitman.lichniy_token.message_decorator', MessageDecoratorChain::class)
        ->args([
            service('unitman.lichniy_token.default_decorator'),
            service(AuthorMessageDecorator::class),
        ]);

    $services->set('unitman.lichniy_token.stream_repository', DoctrineStreamRepository::class)
        ->args([
            service(Connection::class),
            'unitman_lichniy_token_event_store',
            service('unitman.lichniy_token.event_serializer'),
            service('unitman.event_type_mapper'),
            service('unitman.lichniy_token.message_decorator'),
            service('unitman.lichniy_token.message_repository'),
        ]);

    $services->set('unitman.lichniy_token.projections_manager', ProjectionsManager::class)
        ->arg('$projections', tagged_iterator('unitman.lichniy_token.projection'))
        ->arg('$checkpointStore', service('app.checkpoint_store'))
        ->arg('$eventsRepository', service('unitman.lichniy_token.stream_repository'));

    $services->set('unitman.lichniy_token.event_store', EventSourcedAggregateRootRepository::class)
        ->args([
            LichniyToken::class,
            service('unitman.lichniy_token.message_repository'),
            null,
            service('unitman.lichniy_token.message_decorator'),
            service('unitman.event_type_mapper'),
        ]);

    $services->set(SqlLichniyTokenRepository::class)
        ->args([
            service('unitman.lichniy_token.projections_manager'),
        ]);

    $services->alias(LichniyTokenRepository::class, SqlLichniyTokenRepository::class);
    $services->alias(LichniyTokenListRepository::class, SqlLichniyTokenListRepository::class);
};
