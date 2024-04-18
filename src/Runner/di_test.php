<?php

use App\Runner\Business\Port\UmeetProveryatRabotosposobnostGolangRunnera;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services();

    $services->set(\App\Runner\Business\Port\CanGenerateGuid::class)->public();
};

