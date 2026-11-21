<?php

declare(strict_types=1);

namespace App\OpenCode;

use App\OpenCode\Acl\AccountAdapter;
use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Acl\UnitmanAdapter;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Infra\Adapter\RamseyUuidGenerator;
use App\OpenCode\Infra\Repository\SqlHodOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlSessiyaOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use App\OpenCode\Infra\Service\CentrifugoUvedomleniyaOpenCode;
use App\OpenCode\Infra\Service\KanalUvedomleniyOpenCode;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$centrifugoSecret', '%env(CENTRIFUGO_SECRET)%');

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

    // Уведомления о новых сообщениях OpenCode.
    $services->set(UvedomleniyaOpenCodeRepository::class, SqlUvedomleniyaOpenCodeRepository::class);
    $services->set(UmeetPeredavatUvedomleniyaOpenCode::class, CentrifugoUvedomleniyaOpenCode::class);
    $services->set(UmeetOpredelyatKanalUvedomleniyOpenCode::class, KanalUvedomleniyOpenCode::class);

    // Порты бизнес-слоя -> Acl-адаптеры чужих модулей.
    $services->set(UmeetProveryatRekvizityOpenCode::class, AccountAdapter::class);
    $services->set(UmeetVypolnyatHodOpenCode::class, RunnerAdapter::class);
    $services->set(UmeetUpravlyatWorkspace::class, RunnerAdapter::class);
    $services->set(UmeetUpravlyatUnitami::class, UnitmanAdapter::class);
    $services->set(UmeetBronirovatUniti::class, UnitmanAdapter::class);
    $services->set(UmeetUpravlyatProektamiWorkspace::class, UnitmanAdapter::class);
    $services->set(UmeetPoluchatTokenRedaktirovaniyaUnita::class, UnitmanAdapter::class);
};
