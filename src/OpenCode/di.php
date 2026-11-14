<?php

declare(strict_types=1);

namespace App\OpenCode;

use App\OpenCode\Acl\AccountAdapter;
use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Infra\Adapter\RamseyUuidGenerator;
use App\OpenCode\Infra\Repository\SqlHodOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlSessiyaOpenCodeRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\OpenCode\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
        ])
        ->public();

    // Порты бизнес-слоя -> инфраструктурные реализации модуля.
    $services->set(SessiyaOpenCodeRepository::class, SqlSessiyaOpenCodeRepository::class);
    $services->set(HodOpenCodeRepository::class, SqlHodOpenCodeRepository::class);
    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);

    // Порты бизнес-слоя -> Acl-адаптеры чужих модулей.
    $services->set(UmeetProveryatRekvizityOpenCode::class, AccountAdapter::class);
    $services->set(UmeetVypolnyatHodOpenCode::class, RunnerAdapter::class);
};
