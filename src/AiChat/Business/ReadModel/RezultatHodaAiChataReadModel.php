<?php

declare(strict_types=1);

namespace App\AiChat\Business\ReadModel;

/**
 * Результат одного хода AI-чата.
 *
 * Содержит ответ ассистента (текст и вызовы инструментов в плоском виде) и
 * краткие метаданные сессии, которые фронтенд обновляет после хода.
 */
final class RezultatHodaAiChataReadModel
{
    /**
     * @param list<array{id: string, name: string, arguments: string}> $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls,
        public readonly string $sessionId,
        public readonly ?int $providerSessionExpiresAt,
        public readonly bool $archived,
        public readonly int $updatedAt,
        public readonly string $title,
    ) {
    }

    /**
     * @return array{
     *     assistant: array{
     *         content: ?string,
     *         toolCalls: list<array{id: string, name: string, arguments: string}>
     *     },
     *     session: array{
     *         id: string,
     *         providerSessionExpiresAt: ?int,
     *         archived: bool,
     *         updatedAt: int,
     *         title: string
     *     }
     * }
     */
    public function vMassiv(): array
    {
        return [
            'assistant' => [
                'content' => $this->content,
                'toolCalls' => $this->toolCalls,
            ],
            'session' => [
                'id' => $this->sessionId,
                'providerSessionExpiresAt' => $this->providerSessionExpiresAt,
                'archived' => $this->archived,
                'updatedAt' => $this->updatedAt,
                'title' => $this->title,
            ],
        ];
    }
}
