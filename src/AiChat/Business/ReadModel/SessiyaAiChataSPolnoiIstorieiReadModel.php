<?php

declare(strict_types=1);

namespace App\AiChat\Business\ReadModel;

/**
 * Сессия AI-чата вместе с полной историей сообщений.
 *
 * Используется экраном чата: метаданные сессии и вся история в одном ответе.
 */
final class SessiyaAiChataSPolnoiIstorieiReadModel
{
    /**
     * @param list<SoobshenieAiChataReadModel> $soobsheniya
     */
    public function __construct(
        public readonly SessiyaAiChataReadModel $sessiya,
        public readonly array $soobsheniya,
    ) {
    }

    /**
     * @return array{
     *     session: array<string, mixed>,
     *     messages: list<array<string, mixed>>
     * }
     */
    public function vMassiv(): array
    {
        return [
            'session' => $this->sessiya->vMassiv(),
            'messages' => array_map(
                static fn (SoobshenieAiChataReadModel $soobshenie): array => $soobshenie->vMassiv(),
                $this->soobsheniya,
            ),
        ];
    }
}
