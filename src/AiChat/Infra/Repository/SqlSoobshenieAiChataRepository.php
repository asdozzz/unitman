<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Repository;

use App\AiChat\Business\Model\RolSoobsheniyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\Port\SoobshenieAiChataRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * SQL-хранилище истории AI-чата.
 *
 * Сообщения неизменяемы, поэтому запись — только вставка. `tool_calls`
 * хранится в `jsonb`: при непустом значении параметр явно приводится
 * (`cast(:tool_calls as jsonb)`), иначе пишется `null`. Чтение декодирует
 * JSON в исходную вложенную структуру OpenAI без потерь.
 */
final class SqlSoobshenieAiChataRepository implements SoobshenieAiChataRepository
{
    private const TABLE = 'ai_chat_message';

    public function __construct(private Connection $connection)
    {
    }

    public function dobavit(SoobshenieAiChata ...$soobsheniya): void
    {
        if ($soobsheniya === []) {
            return;
        }

        $this->connection->transactional(function () use ($soobsheniya): void {
            foreach ($soobsheniya as $soobshenie) {
                $this->vstavit($soobshenie);
            }
        });
    }

    public function poslednie(string $sessionId, int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where session_id = :session_id order by seq desc limit :limit",
            [
                'session_id' => $sessionId,
                'limit' => max(1, $limit),
            ],
            [
                'session_id' => ParameterType::STRING,
                'limit' => ParameterType::INTEGER,
            ],
        );

        // Запрос берёт последние N в обратном порядке; для потребителя
        // возвращаем историю в естественном порядке возрастания seq.
        return array_map(
            fn (array $row): SoobshenieAiChata => $this->makeSoobshenie($row),
            array_reverse($rows),
        );
    }

    public function vse(string $sessionId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where session_id = :session_id order by seq asc",
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING],
        );

        return array_map(
            fn (array $row): SoobshenieAiChata => $this->makeSoobshenie($row),
            $rows,
        );
    }

    public function kolichestvo(string $sessionId): int
    {
        $table = self::TABLE;

        return (int) $this->connection->fetchOne(
            "select count(*) from $table where session_id = :session_id",
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING],
        );
    }

    public function pervoe(string $sessionId): ?SoobshenieAiChata
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where session_id = :session_id order by seq asc limit 1",
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING],
        );

        return empty($row) ? null : $this->makeSoobshenie($row);
    }

    public function udalitPoSessii(string $sessionId): void
    {
        $this->connection->delete(
            self::TABLE,
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING],
        );
    }

    private function vstavit(SoobshenieAiChata $soobshenie): void
    {
        $toolCalls = $soobshenie->toolCalls;

        // Пустой список вызовов хранится как null: так проще отличить
        // «нет вызовов» от пустого JSON-массива при чтении.
        $toolCallsSql = $toolCalls === [] ? ':tool_calls' : 'cast(:tool_calls as jsonb)';
        $toolCallsValue = $toolCalls === []
            ? null
            : json_encode($toolCalls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $table = self::TABLE;

        $this->connection->executeStatement(
            "insert into $table
                (id, session_id, seq, role, content, tool_calls, tool_call_id, name, created_at)
             values
                (:id, :session_id, :seq, :role, :content, $toolCallsSql, :tool_call_id, :name, :created_at)",
            [
                'id' => $soobshenie->id,
                'session_id' => $soobshenie->sessionId,
                'seq' => $soobshenie->seq,
                'role' => $soobshenie->role->value,
                'content' => $soobshenie->content,
                'tool_calls' => $toolCallsValue,
                'tool_call_id' => $soobshenie->toolCallId,
                'name' => $soobshenie->name,
                'created_at' => $soobshenie->createdAt,
            ],
            [
                'id' => ParameterType::STRING,
                'session_id' => ParameterType::STRING,
                'seq' => ParameterType::INTEGER,
                'role' => ParameterType::STRING,
                'content' => ParameterType::STRING,
                'tool_calls' => ParameterType::STRING,
                'tool_call_id' => ParameterType::STRING,
                'name' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeSoobshenie(array $row): SoobshenieAiChata
    {
        return SoobshenieAiChata::restore(
            (string) $row['id'],
            (string) $row['session_id'],
            (int) $row['seq'],
            RolSoobsheniyaAiChata::tryFrom((string) $row['role']) ?? RolSoobsheniyaAiChata::User,
            \is_string($row['content'] ?? null) ? $row['content'] : null,
            $this->dekodirovatToolCalls($row['tool_calls'] ?? null),
            self::nullableString($row['tool_call_id'] ?? null),
            self::nullableString($row['name'] ?? null),
            (int) $row['created_at'],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dekodirovatToolCalls(mixed $raw): array
    {
        if (!\is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $item): bool => \is_array($item)));
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
