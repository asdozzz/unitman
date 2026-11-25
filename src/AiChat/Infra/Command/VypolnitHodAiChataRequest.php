<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Command;

/**
 * Валидированное тело запроса одного хода AI-чата.
 *
 * HTTP-слой проверяет только форму payload (типы и ровно одно из
 * `message`/`toolResults`), чтобы вернуть 422 до обращения к upstream.
 * Полная проверка длины сообщения, набора инструментов и элементов
 * `toolResults` остаётся в use case — единая точка валидации домена.
 */
final class VypolnitHodAiChataRequest
{
    private const KEY_INVALID_PAYLOAD = 'ai.chat.invalid_payload';

    /**
     * @param list<array{toolCallId: string, content?: string|null, name?: string|null}> $toolResults
     * @param list<array<string, mixed>>                                                  $tools
     */
    private function __construct(
        public readonly ?string $message,
        public readonly array $toolResults,
        public readonly array $tools,
        public readonly ?string $model,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \InvalidArgumentException с ключом `ai.chat.invalid_payload`
     */
    public static function fromArray(array $payload): self
    {
        $message = $payload['message'] ?? null;

        if ($message !== null && !\is_string($message)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        $rawToolResults = $payload['toolResults'] ?? [];

        if (!\is_array($rawToolResults) || !array_is_list($rawToolResults)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        $toolResults = self::normalizeToolResults($rawToolResults);

        $rawTools = $payload['tools'] ?? [];

        if (!\is_array($rawTools) || !array_is_list($rawTools)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        $tools = [];

        foreach ($rawTools as $tool) {
            if (!\is_array($tool)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $tools[] = $tool;
        }

        $model = $payload['model'] ?? null;

        if ($model !== null && !\is_string($model)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        $hasMessage = $message !== null && trim($message) !== '';
        $hasToolResults = $toolResults !== [];

        if ($hasMessage === $hasToolResults) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        return new self($message, $toolResults, $tools, $model);
    }

    /**
     * @param array<array-key, mixed> $rawToolResults
     *
     * @return list<array{toolCallId: string, content?: string|null, name?: string|null}>
     */
    private static function normalizeToolResults(array $rawToolResults): array
    {
        $result = [];

        foreach ($rawToolResults as $item) {
            if (!\is_array($item)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $toolCallId = $item['toolCallId'] ?? null;

            if (!\is_string($toolCallId) || trim($toolCallId) === '') {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $content = $item['content'] ?? null;

            if ($content !== null && !\is_string($content)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $name = $item['name'] ?? null;

            if ($name !== null && !\is_string($name)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $result[] = [
                'toolCallId' => $toolCallId,
                'content' => $content,
                'name' => $name,
            ];
        }

        return $result;
    }
}
