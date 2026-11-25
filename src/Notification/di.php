<?php

declare(strict_types=1);

namespace App\Notification;

use App\Notification\Acl\AccountAdapter;
use App\Notification\Business\Port\CanGenerateGuid;
use App\Notification\Business\Port\DostavkiRepository;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\PushPodpiskiRepository;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\Port\UmeetOtpravlyatPush;
use App\Notification\Business\Port\UmeetPoluchatAdministratorov;
use App\Notification\Business\Port\UvedomleniyaRepository;
use App\Notification\Infra\Adapter\RamseyGuidGenerator;
use App\Notification\Infra\Jobs\NotificationPushJobHandler;
use App\Notification\Infra\Repository\SqlDostavkiRepository;
use App\Notification\Infra\Repository\SqlNastroikiUvedomleniyRepository;
use App\Notification\Infra\Repository\SqlPushPodpiskiRepository;
use App\Notification\Infra\Repository\SqlUvedomleniyaRepository;
use App\Notification\Infra\Service\WebPushPeredavatel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$roadRunnerRpcAddress', '%env(RR_RPC)%')
        ->bind('$vapidPublicKey', '%env(default::VAPID_PUBLIC_KEY)%')
        ->bind('$vapidPrivateKey', '%env(default::VAPID_PRIVATE_KEY)%')
        ->bind('$vapidSubject', '%env(default::VAPID_SUBJECT)%')
        ->bind(
            '$infoRetentionDays',
            '%env(default:notification_info_retention_days:NOTIFICATION_INFO_RETENTION_DAYS)%'
        )
        ->bind(
            '$actionableRetentionDays',
            '%env(default:notification_actionable_retention_days:NOTIFICATION_ACTIONABLE_RETENTION_DAYS)%'
        );

    $services->load('App\\Notification\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
        ])
        ->public();

    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class);
    $services->set(UvedomleniyaRepository::class, SqlUvedomleniyaRepository::class);
    $services->set(NastroikiUvedomleniyRepository::class, SqlNastroikiUvedomleniyRepository::class);
    $services->set(DostavkiRepository::class, SqlDostavkiRepository::class);
    $services->set(PushPodpiskiRepository::class, SqlPushPodpiskiRepository::class);
    $services->set(\Minishlink\WebPush\WebPush::class)
        ->args([[
            'VAPID' => [
                'subject' => '%env(default::VAPID_SUBJECT)%',
                'publicKey' => '%env(default::VAPID_PUBLIC_KEY)%',
                'privateKey' => '%env(default::VAPID_PRIVATE_KEY)%',
            ],
        ]]);
    $services->set(UmeetOtpravlyatPush::class, WebPushPeredavatel::class);
    $services->set(UmeetOpredelyatPolzovatelya::class, AccountAdapter::class);
    $services->set(UmeetPoluchatAdministratorov::class, AccountAdapter::class);

    $services->set(NotificationPushJobHandler::class)
        ->tag('roadrunner_jobs.handler');
};
