<?php

declare(strict_types=1);

namespace App\AiChat;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        // URL из реквизитов пользователя имеет приоритет; env — только fallback.
        ->bind('$openCodeApiUrl', '%env(OPENCODE_API_URL)%')
        // Таймаут исходящих запросов к OpenAI-совместимому API, секунды.
        ->bind('$timeout', '%env(float:AI_CHAT_TIMEOUT)%');

    $services->load('App\\AiChat\\', './{Infra}')
        // DTO запроса и тестовые заглушки не являются сервисами.
        ->exclude(['./{di.php,di_test.php,routing.php,Tests}', './Infra/Command'])
        ->public();
};
