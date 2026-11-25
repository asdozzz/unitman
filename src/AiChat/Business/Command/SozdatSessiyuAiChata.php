<?php

declare(strict_types=1);

namespace App\AiChat\Business\Command;

/**
 * Команда создания новой сессии AI-чата.
 *
 * Все параметры необязательны: без `credentialId` берётся первый реквизит
 * пользователя, без `model` — модель по умолчанию реквизитов, без `title`
 * заголовок остаётся пустым до первого сообщения.
 */
readonly class SozdatSessiyuAiChata
{
    public function __construct(
        public ?string $credentialId = null,
        public ?string $model = null,
        public ?string $title = null,
    ) {
    }
}
