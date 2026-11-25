<?php

declare(strict_types=1);

namespace App\AiChat\Business\Command;

use App\AiChat\Business\Model\FiltrSessiyAiChata;

/**
 * Команда запроса списка сессий AI-чата аккаунта.
 *
 * `accountId` фиксируется вызывающим слоем (контроллером) из контекста
 * аутентификации, а фильтр описывает пагинацию, поиск и архивные.
 */
readonly class PoluchitSpisokSessiyAiChata
{
    public function __construct(
        public string $accountId,
        public FiltrSessiyAiChata $filtr,
    ) {
    }
}
