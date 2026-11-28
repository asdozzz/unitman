<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository\UnitSession;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\UnitSession\Port\UnitSessionTurnRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlUnitSessionTurnRepository implements UnitSessionTurnRepository
{
    private const TABLE = 'opencode_unit_session_turn';

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
                'model' => $turn->getModel(),
                'agent_mode_id' => $turn->getAgentModeId(),
                'agent_mode_name' => $turn->getAgentModeName() ?? '',
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
                'model' => ParameterType::STRING,
                'agent_mode_id' => ParameterType::STRING,
                'agent_mode_name' => ParameterType::STRING,
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
            "select * from $table where id = :id",
            ['id' => $id]
        );

        return empty($row) ? null : $this->makeTurn($row);
    }

    public function findByWorkflowId(string $workflowId): ?HodOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where workflow_id = :workflow_id",
            ['workflow_id' => $workflowId]
        );

        return empty($row) ? null : $this->makeTurn($row);
    }

    public function findBySessionId(string $sessionId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where session_id = :session_id order by seq",
            ['session_id' => $sessionId]
        );

        return array_map(fn (array $row): HodOpenCode => $this->makeTurn($row), $rows);
    }

    public function findVProzesse(int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where status = 'RUNNING' order by created_at limit :limit",
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER]
        );

        return array_map(fn (array $row): HodOpenCode => $this->makeTurn($row), $rows);
    }

    public function findPosledniiPoSessii(string $sessionId): ?HodOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where session_id = :session_id order by seq desc limit 1",
            ['session_id' => $sessionId]
        );

        return empty($row) ? null : $this->makeTurn($row);
    }

    public function sleduyushiiSeq(string $sessionId): int
    {
        $table = self::TABLE;
        $seq = $this->connection->fetchOne(
            "select coalesce(max(seq), 0) from $table where session_id = :session_id",
            ['session_id' => $sessionId]
        );

        return (int) $seq + 1;
    }

    private function encodeAnswers(HodOpenCode $turn): string
    {
        return json_encode($turn->getAnswers(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @param array<string, mixed> $row
     */
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
            is_string($row['model'] ?? null) && $row['model'] !== '' ? (string) $row['model'] : null,
            is_string($row['agent_mode_id'] ?? null) && $row['agent_mode_id'] !== ''
                ? (string) $row['agent_mode_id']
                : null,
            is_string($row['agent_mode_name'] ?? null) && $row['agent_mode_name'] !== ''
                ? (string) $row['agent_mode_name']
                : null,
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
