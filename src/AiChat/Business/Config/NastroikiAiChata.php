<?php

declare(strict_types=1);

namespace App\AiChat\Business\Config;

/**
 * Скалярные настройки AI-чата из окружения.
 *
 * Неизменяемый контейнер значений, чтобы use case могли получать TTL и лимит
 * контекста через DI, а `di.php` — привязать env-переменные к конкретным
 * аргументам без дублирования `%env(...)%` в каждом сервисе.
 *
 * Класс живёт в Business-слое, чтобы use case не зависели от Infra.
 */
final class NastroikiAiChata
{
    public function __construct(
        public readonly int $chatSessionTtl,
        public readonly int $providerSessionMaxTtl,
        public readonly int $maxContextMessages,
    ) {
    }
}
