<?php

declare(strict_types=1);

namespace App\AiChat\Business\Model;

/**
 * Сообщение AI-чата — неизменяемый value object.
 *
 * Хранит роль, текст, вызовы инструментов ассистента (вложенная структура
 * OpenAI) и связь tool-результата с конкретным вызовом. Умеет отдавать себя
 * в формате OpenAI chat completions для передачи в upstream.
 */
final class SoobshenieAiChata
{
    /**
     * @param list<array<string, mixed>> $toolCalls
     */
    private function __construct(
        public readonly string $id,
        public readonly string $sessionId,
        public readonly int $seq,
        public readonly RolSoobsheniyaAiChata $role,
        public readonly ?string $content,
        public readonly array $toolCalls,
        public readonly ?string $toolCallId,
        public readonly ?string $name,
        public readonly int $createdAt,
    ) {
    }

    public static function polzovatelya(
        string $id,
        string $sessionId,
        int $seq,
        string $content,
        int $createdAt,
    ): self {
        return new self($id, $sessionId, $seq, RolSoobsheniyaAiChata::User, $content, [], null, null, $createdAt);
    }

    /**
     * @param list<array<string, mixed>> $toolCalls
     */
    public static function assistenta(
        string $id,
        string $sessionId,
        int $seq,
        ?string $content,
        array $toolCalls,
        int $createdAt,
    ): self {
        return new self($id, $sessionId, $seq, RolSoobsheniyaAiChata::Assistant, $content, $toolCalls, null, null, $createdAt);
    }

    public static function instrumenta(
        string $id,
        string $sessionId,
        int $seq,
        string $toolCallId,
        string $content,
        ?string $name,
        int $createdAt,
    ): self {
        return new self($id, $sessionId, $seq, RolSoobsheniyaAiChata::Tool, $content, [], $toolCallId, $name, $createdAt);
    }

    public static function sistemnaya(
        string $id,
        string $sessionId,
        int $seq,
        string $content,
        int $createdAt,
    ): self {
        return new self($id, $sessionId, $seq, RolSoobsheniyaAiChata::System, $content, [], null, null, $createdAt);
    }

    /**
     * @param list<array<string, mixed>> $toolCalls
     */
    public static function restore(
        string $id,
        string $sessionId,
        int $seq,
        RolSoobsheniyaAiChata $role,
        ?string $content,
        array $toolCalls,
        ?string $toolCallId,
        ?string $name,
        int $createdAt,
    ): self {
        return new self($id, $sessionId, $seq, $role, $content, $toolCalls, $toolCallId, $name, $createdAt);
    }

    /**
     * Представление сообщения в формате OpenAI chat completions.
     *
     * @return array<string, mixed>
     */
    public function vFormatOpenAi(): array
    {
        $message = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];

        if ($this->toolCalls !== []) {
            $message['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $message['tool_call_id'] = $this->toolCallId;
        }

        if ($this->name !== null) {
            $message['name'] = $this->name;
        }

        return $message;
    }
}
