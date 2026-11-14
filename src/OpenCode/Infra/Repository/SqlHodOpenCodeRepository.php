<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlHodOpenCodeRepository implements HodOpenCodeRepository
{
    private const TABLE = 'opencode_turn';

    public function __construct(private Connection $connection)
    {
    }

    public function save(HodOpenCode $turn): void
    {
        $result = $turn->getResult();

        $this->connection->insert(
            self::TABLE,
            [
                'id' => $turn->getId(),
                'session_id' => $turn->getSessionId(),
                'seq' => $turn->getSeq(),
                'workflow_id' => $turn->getWorkflowId(),
                'status' => $turn->getStatus()->value,
                'request_text' => $turn->getRequestText(),
                'answers_json' => $this->encodeAnswers($turn),
                'result_json' => $result !== null
                    ? json_encode($result->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'error_key' => $turn->getErrorKey(),
                'created_at' => $turn->getCreatedAt(),
                'finished_at' => $turn->getFinishedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'session_id' => ParameterType::STRING,
                'seq' => ParameterType::INTEGER,
                'workflow_id' => ParameterType::STRING,
                'status' => ParameterType::STRING,
                'request_text' => ParameterType::STRING,
                'answers_json' => ParameterType::STRING,
                'result_json' => ParameterType::STRING,
                'error_key' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'finished_at' => ParameterType::INTEGER,
            ]
        );
    }

    public function update(HodOpenCode $turn): void
    {
        $result = $turn->getResult();

        $this->connection->update(
            self::TABLE,
            [
                'status' => $turn->getStatus()->value,
                'answers_json' => $this->encodeAnswers($turn),
                'result_json' => $result !== null
                    ? json_encode($result->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'error_key' => $turn->getErrorKey(),
                'finished_at' => $turn->getFinishedAt(),
            ],
            ['id' => $turn->getId()],
            [
                'status' => ParameterType::STRING,
                'answers_json' => ParameterType::STRING,
                'result_json' => ParameterType::STRING,
                'error_key' => ParameterType::STRING,
                'finished_at' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
            ]
        );
    }

    public function getById(string $id): HodOpenCode
    {
        $turn = $this->findById($id);

        if ($turn === null) {
            throw new \DomainException('opencode.turn.not_found');
        }

        return $turn;
    }

    public function findById(string $id): ?HodOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE id = :id",
            ['id' => $id]
        );

        return empty($row) ? null : $this->makeTurn($row);
    }

    public function findByWorkflowId(string $workflowId): ?HodOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE workflow_id = :workflow_id",
            ['workflow_id' => $workflowId]
        );

        return empty($row) ? null : $this->makeTurn($row);
    }

    public function findBySessionId(string $sessionId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE session_id = :session_id ORDER BY seq",
            ['session_id' => $sessionId]
        );

        return array_map(fn (array $row): HodOpenCode => $this->makeTurn($row), $rows);
    }

    public function findPosledniiPoSessii(string $sessionId): ?HodOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE session_id = :session_id ORDER BY seq DESC LIMIT 1",
            ['session_id' => $sessionId]
        );

        return empty($row) ? null : $this->makeTurn($row);
    }

    public function sleduyushiiSeq(string $sessionId): int
    {
        $table = self::TABLE;
        $seq = $this->connection->fetchOne(
            "SELECT COALESCE(MAX(seq), 0) FROM $table WHERE session_id = :session_id",
            ['session_id' => $sessionId]
        );

        return (int) $seq + 1;
    }

    private function encodeAnswers(HodOpenCode $turn): string
    {
        return json_encode($turn->getAnswers(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function makeTurn(array $row): HodOpenCode
    {
        return HodOpenCode::restore(
            (string) $row['id'],
            (string) $row['session_id'],
            (int) $row['seq'],
            (string) $row['workflow_id'],
            (string) $row['status'],
            (string) $row['request_text'],
            $this->decodeAnswers($row['answers_json'] ?? null),
            $this->decodeResult($row['result_json'] ?? null),
            is_string($row['error_key'] ?? null) && $row['error_key'] !== '' ? (string) $row['error_key'] : null,
            (int) $row['created_at'],
            isset($row['finished_at']) ? (int) $row['finished_at'] : null,
        );
    }

    /**
     * @return list<array{QuestionId: string, OptionId: string|null, Text: string|null}>
     */
    private function decodeAnswers(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $answers = [];

        foreach ($decoded as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $answers[] = [
                'QuestionId' => (string) ($answer['QuestionId'] ?? ''),
                'OptionId' => isset($answer['OptionId']) ? (string) $answer['OptionId'] : null,
                'Text' => isset($answer['Text']) ? (string) $answer['Text'] : null,
            ];
        }

        return $answers;
    }

    private function decodeResult(mixed $raw): ?RezultatHodaOpenCode
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        return RezultatHodaOpenCode::fromArray($decoded);
    }
}