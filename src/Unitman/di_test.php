<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Repo\CanCheckAccessToRepo;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services();

    $services->set(UnitmanSecurityService::class)->public();
    $services->set(CanGeneateGuid::class)->public();
    $services->set(CanCheckAccessToRepo::class)->public();
    //$services->set(RunnerService::class)->public();
};
