<?php

declare(strict_types=1);

namespace App\AiChat;

use App\AiChat\Business\Port\OtpravitelChatZaprosov;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\Port\SoobshenieAiChataRepository;
use App\AiChat\Business\Port\TransactionManager;
use App\AiChat\Business\Port\UuidGenerator;
use App\AiChat\Infra\Adapter\AccountRekvizityDlyaSessiiAiChata;
use App\AiChat\Infra\Adapter\DbalTransactionManager;
use App\AiChat\Infra\Adapter\OpenCodeChatProxyOtpravitel;
use App\AiChat\Infra\Adapter\RamseyUuidGenerator;
use App\AiChat\Infra\Repository\SqlSessiyaAiChataRepository;
use App\AiChat\Infra\Repository\SqlSoobshenieAiChataRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        // URL из реквизитов пользователя имеет приоритет; env — только fallback.
        ->bind('$openCodeApiUrl', '%env(OPENCODE_API_URL)%')
        // Таймаут исходящих запросов к OpenAI-совместимому API, секунды.
        ->bind('$timeout', '%env(float:AI_CHAT_TIMEOUT)%')
        // TTL пользовательской сессии AI-чата, секунды (по умолчанию 24 часа).
        ->bind('$chatSessionTtl', '%env(int:AI_CHAT_SESSION_TTL)%')
        // Верхняя граница TTL provider-сессии, секунды.
        ->bind('$providerSessionMaxTtl', '%env(int:AI_CHAT_PROVIDER_SESSION_MAX_TTL)%')
        // Сколько последних сообщений передавать в контекст upstream.
        ->bind('$maxContextMessages', '%env(int:AI_CHAT_MAX_CONTEXT_MESSAGES)%');

    $services->load('App\\AiChat\\', './{Business,Infra}')
        // DTO, доменные модели, read-model, миграции и тестовые заглушки
        // не являются сервисами.
        ->exclude([
            './{di.php,di_test.php,routing.php,Tests}',
            './Business/Model',
            './Business/ReadModel',
            './Business/Command',
            './Migrations',
            './Infra/Command',
        ])
        ->public();

    // Порты бизнес-слоя -> инфраструктурные реализации модуля.
    $services->set(SessiyaAiChataRepository::class, SqlSessiyaAiChataRepository::class);
    $services->set(SoobshenieAiChataRepository::class, SqlSoobshenieAiChataRepository::class);
    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);
    $services->set(TransactionManager::class, DbalTransactionManager::class);

    // Отправка chat-запроса в upstream -> адаптер над final-прокси.
    $services->set(OtpravitelChatZaprosov::class, OpenCodeChatProxyOtpravitel::class);

    // Узкий порт реквизитов -> Acl-адаптер над AccountApi.
    $services->set(RekvizityDlyaSessiiAiChata::class, AccountRekvizityDlyaSessiiAiChata::class);
};
