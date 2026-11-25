<?php

declare(strict_types=1);

namespace App\AiChat\Business\ReadModel;

use App\AiChat\Business\Model\SoobshenieAiChata;

/**
 * Сообщение AI-чата для API.
 *
 * В отличие от домена, `toolCalls` отдаются в плоском виде
 * (`[{id, name, arguments}]`), удобном фронтенду и не привязанном к
 * вложенной структуре OpenAI.
 */
final class SoobshenieAiChataReadModel
{
    /**
     * @param list<array{id: string, name: string, arguments: string}> $toolCalls
     */
    public function __construct(
        public readonly string $id,
        public readonly int $seq,
        public readonly string $role,
        public readonly ?string $content,
        public readonly array $toolCalls,
        public readonly ?string $toolCallId,
        public readonly ?string $name,
        public readonly int $createdAt,
    ) {
    }

    public static function izDomena(SoobshenieAiChata $soobshenie): self
    {
        return new self(
            $soobshenie->id,
            $soobshenie->seq,
            $soobshenie->role->value,
            $soobshenie->content,
            self::ploskieToolCalls($soobshenie->toolCalls),
            $soobshenie->toolCallId,
            $soobshenie->name,
            $soobshenie->createdAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function izStroki(array $row): self
    {
        $toolCalls = $row['tool_calls'] ?? null;

        return new self(
            (string) ($row['id'] ?? ''),
            (int) ($row['seq'] ?? 0),
            (string) ($row['role'] ?? ''),
            self::nullableString($row['content'] ?? null),
            self::ploskieToolCalls(self::dekodirovatToolCalls($toolCalls)),
            self::nullableString($row['tool_call_id'] ?? null),
            self::nullableString($row['name'] ?? null),
            (int) ($row['created_at'] ?? 0),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     seq: int,
     *     role: string,
     *     content: ?string,
     *     toolCalls: list<array{id: string, name: string, arguments: string}>,
     *     toolCallId: ?string,
     *     name: ?string,
     *     createdAt: int
     * }
     */
    public function vMassiv(): array
    {
        return [
            'id' => $this->id,
            'seq' => $this->seq,
            'role' => $this->role,
            'content' => $this->content,
            'toolCalls' => $this->toolCalls,
            'toolCallId' => $this->toolCallId,
            'name' => $this->name,
            'createdAt' => $this->createdAt,
        ];
    }

    /**
     * Привести вызовы инструментов к плоскому виду `[{id, name, arguments}]`.
     *
     * Принимает как вложенный формат OpenAI (`function.name/arguments`), так и
     * уже плоский — чтобы чтение из БД и из домена давало одинаковый результат.
     *
     * @param array<array-key, mixed> $toolCalls
     *
     * @return list<array{id: string, name: string, arguments: string}>
     */
    public static function ploskieToolCalls(array $toolCalls): array
    {
        $result = [];

        foreach ($toolCalls as $call) {
            if (!\is_array($call)) {
                continue;
            }

            $function = \is_array($call['function'] ?? null) ? $call['function'] : [];

            $result[] = [
                'id' => self::stroka($call['id'] ?? null),
                'name' => self::stroka($call['name'] ?? ($function['name'] ?? null)),
                'arguments' => self::stroka($call['arguments'] ?? ($function['arguments'] ?? null)),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function dekodirovatToolCalls(mixed $raw): array
    {
        if (\is_array($raw)) {
            return array_values(array_filter($raw, static fn (mixed $item): bool => \is_array($item)));
        }

        if (!\is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $item): bool => \is_array($item)));
    }

    private static function stroka(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
