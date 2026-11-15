<?php

declare(strict_types=1);

namespace App\Unitman;

use App\Unitman\Acl\AccountAdapter;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Repo\CanCheckAccessToRepo;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services();

    // В тестовом окружении повторяем продовую привязку порта к адаптеру:
    // иначе сервис нельзя инстанцировать, и любой запрос ядра (WebTestCase)
    // падает с "Cannot instantiate interface". Юнит-тесты по-прежнему
    // перекрывают id через self::$container->set(...).
    $services->set(UnitmanSecurityService::class, AccountAdapter::class)->autowire()->public();
    $services->set(CanGenerateGuid::class, RamseyGuidGenerator::class)->public();
    $services->set(CanCheckAccessToRepo::class)->public();
    //$services->set(RunnerService::class)->public();
};
