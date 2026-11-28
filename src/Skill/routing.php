<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Контроллеров пока нет: первый рабочей пакет добавляет только домен,
    // порт и SQL-хранилище. Импорт оставлен на будущее, чтобы новый модуль
    // подключался по общему шаблону.
    $routes->import('./Infra/Controller', 'attribute');
};
