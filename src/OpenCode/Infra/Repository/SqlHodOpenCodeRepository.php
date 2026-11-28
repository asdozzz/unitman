<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class SqlHodOpenCodeRepository implements HodOpenCodeRepository
{
    private const TABLE = 'opencode_turn';

    public function __construct(
        private Connection $connection,
        private ?WorkspaceMirror $mirror = null,
        private ?LoggerInterface $logger = null,
    ) {
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
                // Колонки NOT NULL DEFAULT '': пустая строка означает
                // «автор неизвестен» (исторические ходы и unit-сессии).
                'author_account_id' => $turn->getAuthorAccountId() ?? '',
                'credential_id' => $turn->getCredentialId() ?? '',
                'author_display_name' => $turn->getAuthorDisplayName() ?? '',
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
                'author_account_id' => ParameterType::STRING,
                'credential_id' => ParameterType::STRING,
                'author_display_name' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'finished_at' => ParameterType::INTEGER,
            ]
        );

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitHod($turn->getId()));
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

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitHod($turn->getId()));
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

    public function findVProzesse(int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table
             WHERE status = 'RUNNING'
             ORDER BY created_at
             LIMIT :limit",
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER]
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

    public function zanyatSessiyu(string $sessionId, string $turnId, int $now, int $ustarevanieSekund): void
    {
        // Один upsert вместо «select + insert»: проверка свободы сессии и
        // запись аренды атомарны на уровне PostgreSQL. Условие в do update
        // пропускает только просроченную аренду, свежая остаётся нетронутой.
        $affected = $this->connection->executeStatement(
            'insert into opencode_session_running_turn (session_id, turn_id, created_at)
             values (:session_id, :turn_id, :now)
             on conflict (session_id) do update
             set turn_id = excluded.turn_id,
                 created_at = excluded.created_at
             where opencode_session_running_turn.created_at < :stale_before',
            [
                'session_id' => $sessionId,
                'turn_id' => $turnId,
                'now' => $now,
                'stale_before' => $now - $ustarevanieSekund,
            ],
            [
                'session_id' => ParameterType::STRING,
                'turn_id' => ParameterType::STRING,
                'now' => ParameterType::INTEGER,
                'stale_before' => ParameterType::INTEGER,
            ]
        );

        if ($affected === 0) {
            throw new \DomainException('opencode.session.busy');
        }
    }

    public function osvoboditSessiyu(string $sessionId, string $turnId): void
    {
        // Чужой захват не снимается: turn_id в условии обязателен.
        $this->connection->delete(
            'opencode_session_running_turn',
            [
                'session_id' => $sessionId,
                'turn_id' => $turnId,
            ],
            [
                'session_id' => ParameterType::STRING,
                'turn_id' => ParameterType::STRING,
            ]
        );
    }

    public function zanyata(string $sessionId, int $now, int $ustarevanieSekund): bool
    {
        $occupied = $this->connection->fetchOne(
            'select 1 from opencode_session_running_turn
             where session_id = :session_id and created_at >= :fresh_after
             limit 1',
            [
                'session_id' => $sessionId,
                'fresh_after' => $now - $ustarevanieSekund,
            ],
            [
                'session_id' => ParameterType::STRING,
                'fresh_after' => ParameterType::INTEGER,
            ]
        );

        return $occupied !== false;
    }

    /**
     * Best-effort зеркало: ошибка не должна ломать легаси-операцию.
     */
    private function zerkalit(callable $deistvie): void
    {
        if ($this->mirror === null || !$this->mirror->isEnabled()) {
            return;
        }

        try {
            $deistvie($this->mirror);
        } catch (\Throwable $exception) {
            $this->logger?->error('opencode.workspace.mirror_failed', [
                'error' => $exception->getMessage(),
            ]);
        }
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
            is_string($row['model'] ?? null) && $row['model'] !== '' ? (string) $row['model'] : null,
            is_string($row['author_account_id'] ?? null) && $row['author_account_id'] !== ''
                ? (string) $row['author_account_id']
                : null,
            is_string($row['credential_id'] ?? null) && $row['credential_id'] !== ''
                ? (string) $row['credential_id']
                : null,
            is_string($row['author_display_name'] ?? null) && $row['author_display_name'] !== ''
                ? (string) $row['author_display_name']
                : null,
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