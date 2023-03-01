<?php

declare(strict_types=1);

namespace App\Account;

use App\Account\Business\Port\SecurityService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services();

    $services->set(SecurityService::class)->public();
};
