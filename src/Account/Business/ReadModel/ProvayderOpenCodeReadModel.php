<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Элемент каталога провайдеров для фронтенда формы реквизитов OpenCode.
 *
 * Список моделей в каталог не входит: он запрашивается у провайдера, а для
 * провайдеров без live-списка модели вводятся вручную в форме реквизитов.
 * Поля `session*` и `supportsAiChat` описывают политику provider-сессии и
 * позволяют UI блокировать провайдеров без настроенного транспорта.
 */
final class ProvayderOpenCodeReadModel
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $baseUrl,
        public readonly string $modelsPath,
        public readonly string $authStyle,
        public readonly bool $supportsModelList,
        public readonly string $sessionTransport,
        public readonly ?string $sessionKey,
        public readonly int $sessionTtlSeconds,
        public readonly bool $supportsAiChat,
    ) {
    }
}
