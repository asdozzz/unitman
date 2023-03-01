<?php

declare(strict_types=1);

namespace App\Utils;

use App\Utils\Converter\BaseExceptionListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\Utils\\', './')
        ->exclude(['./{di.php, routing.php, Tests}','./Model/'])
        ->public();
};
