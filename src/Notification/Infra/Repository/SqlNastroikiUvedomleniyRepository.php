<?php

declare(strict_types=1);

namespace App\Notification\Infra\Repository;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlNastroikiUvedomleniyRepository implements NastroikiUvedomleniyRepository
{
    private const TABLE = 'notification_preference';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findDlyaAccounta(string $accountId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select type, enabled from $table where account_id = :account_id",
            ['account_id' => $accountId],
        );

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['type']] = (bool) $row['enabled'];
        }

        return $result;
    }

    public function findOtkluchennih(TipUvedomleniya $type, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $table = self::TABLE;
        $rows = $this->connection->fetchFirstColumn(
            "select account_id from $table
             where type = :type and enabled = false and account_id in (:account_ids)",
            ['type' => $type->value, 'account_ids' => $accountIds],
            ['type' => ParameterType::STRING, 'account_ids' => ArrayParameterType::STRING],
        );

        return array_map(static fn (mixed $id): string => (string) $id, $rows);
    }

    public function save(string $accountId, TipUvedomleniya $type, bool $enabled, int $now): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "insert into $table (account_id, type, enabled, updated_at)
             values (:account_id, :type, :enabled, :updated_at)
             on conflict (account_id, type)
             do update set enabled = excluded.enabled, updated_at = excluded.updated_at",
            [
                'account_id' => $accountId,
                'type' => $type->value,
                'enabled' => $enabled,
                'updated_at' => $now,
            ],
            [
                'account_id' => ParameterType::STRING,
                'type' => ParameterType::STRING,
                'enabled' => ParameterType::BOOLEAN,
                'updated_at' => ParameterType::INTEGER,
            ],
        );
    }
}
