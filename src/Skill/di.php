<?php

declare(strict_types=1);

namespace App\Skill;

use App\Skill\Acl\PravaNavykovAdapter;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Port\PravaNavykov;
use App\Skill\Business\Port\UuidGenerator;
use App\Skill\Infra\Adapter\RamseyUuidGenerator;
use App\Skill\Infra\Repository\SqlNavykRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration): void {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Skill\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
        ])
        ->public();

    // Порты бизнес-слоя -> реализации модуля.
    $services->set(NavykRepository::class, SqlNavykRepository::class);
    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);
    $services->set(PravaNavykov::class, PravaNavykovAdapter::class);
};
