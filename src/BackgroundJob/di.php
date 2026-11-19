<?php

declare(strict_types=1);

namespace App\BackgroundJob;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        // Адрес RPC-сокета RoadRunner: единый источник — переменная RR_RPC,
        // она же подставляется в .rr.yaml (rpc.listen).
        ->bind('$roadRunnerRpcAddress', '%env(RR_RPC)%')
        // Флаг режима планировщика. default:... нужен, чтобы старый .env без
        // BACKGROUND_JOB_SCHEDULER продолжал загружаться; значение по умолчанию
        // объявлено в config/packages/background_job.yaml.
        ->bind(
            '$backgroundJobScheduler',
            '%env(default:background_job_scheduler_default:BACKGROUND_JOB_SCHEDULER)%'
        );

    $services->load('App\\BackgroundJob\\', './{Business,Infra,Acl,Api}')
        ->exclude(['./{di.php,di_test.php,routing.php,Migrations,Tests}', './Business/Command', './Business/Model', './Business/ReadModel'])
        ->public();
};
