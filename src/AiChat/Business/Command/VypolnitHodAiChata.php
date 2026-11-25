<?php

declare(strict_types=1);

namespace App\AiChat\Business\Command;

/**
 * Команда одного хода AI-чата.
 *
 * Ход — это либо новое сообщение пользователя (`message`), либо результаты
 * выполнения инструментов (`toolResults`), ровно одно из двух. Валидация
 * формата выполняется в use case, команда только переносит данные.
 */
readonly class VypolnitHodAiChata
{
    /**
     * @param list<array{toolCallId: string, content?: string|null, name?: string|null}> $toolResults
     * @param list<array<string, mixed>>                                                  $tools       OpenAI-описания инструментов
     */
    public function __construct(
        public string $sessionId,
        public ?string $message = null,
        public array $toolResults = [],
        public array $tools = [],
        public ?string $model = null,
    ) {
    }
}
