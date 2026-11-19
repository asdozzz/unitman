<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlUvedomleniyaOpenCodeRepository implements UvedomleniyaOpenCodeRepository
{
    private const TABLE = 'opencode_notification';

    public function __construct(private Connection $connection)
    {
    }

    public function save(UvedomlenieONovomSoobsheniiOpenCode $notification): bool
    {
        $table = self::TABLE;
        $affected = $this->connection->executeStatement(
            "insert into $table
                (id, account_id, session_id, turn_id, session_title, status, kind, created_at, read_at)
             values
                (:id, :account_id, :session_id, :turn_id, :session_title, :status, :kind, :created_at, null)
             on conflict (turn_id) do nothing",
            [
                'id' => $notification->id,
                'account_id' => $notification->accountId,
                'session_id' => $notification->sessionId,
                'turn_id' => $notification->turnId,
                'session_title' => $notification->sessionTitle,
                'status' => $notification->status,
                'kind' => $notification->kind,
                'created_at' => $notification->createdAt,
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'session_id' => ParameterType::STRING,
                'turn_id' => ParameterType::STRING,
                'session_title' => ParameterType::STRING,
                'status' => ParameterType::STRING,
                'kind' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ]
        );

        return $affected > 0;
    }

    public function findNeprochitannie(string $accountId, int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table
             where account_id = :account_id and read_at is null
             order by created_at, id
             limit :limit",
            ['account_id' => $accountId, 'limit' => $limit],
            ['account_id' => ParameterType::STRING, 'limit' => ParameterType::INTEGER]
        );

        return array_map(
            static fn (array $row): UvedomlenieONovomSoobsheniiOpenCode => self::izStroki($row),
            $rows
        );
    }

    public function zahvatitNeprochitannie(string $accountId, array $ids, int $now): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $table = self::TABLE;
        $rows = $this->connection->executeQuery(
            "update $table set read_at = :now
             where account_id = :account_id and read_at is null and id in (:ids)
             returning id",
            ['now' => $now, 'account_id' => $accountId, 'ids' => $ids],
            [
                'now' => ParameterType::INTEGER,
                'account_id' => ParameterType::STRING,
                'ids' => ArrayParameterType::STRING,
            ]
        )->fetchFirstColumn();

        return array_map(static fn (mixed $id): string => (string) $id, $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function izStroki(array $row): UvedomlenieONovomSoobsheniiOpenCode
    {
        $kind = $row['kind'] ?? null;

        return new UvedomlenieONovomSoobsheniiOpenCode(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['session_id'],
            (string) $row['turn_id'],
            (string) $row['session_title'],
            (string) $row['status'],
            is_string($kind) && $kind !== '' ? $kind : null,
            (int) $row['created_at'],
        );
    }
}
