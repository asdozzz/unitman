<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Unitman\Business\Port\CanCheckAccessToRepo;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\SecurityService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services();

    $services->set(SecurityService::class)->public();
    $services->set(CanGeneateGuid::class)->public();
    $services->set(CanCheckAccessToRepo::class)->public();
};
