<?php

declare(strict_types=1);

namespace App\Account\Infra\Repository;

use App\Account\Business\Port\OAuthExchangeCodeRepository;
use Doctrine\DBAL\Connection;

final class SqlOAuthExchangeCodeRepository implements OAuthExchangeCodeRepository
{
    private const TABLE = 'oauth_exchange_code';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(string $codeHash, string $accountId, int $expiresAt, int $createdAt): void
    {
        $table = self::TABLE;

        $this->connection->insert($table, [
            'code_hash' => $codeHash,
            'account_id' => $accountId,
            'expires_at' => gmdate('Y-m-d H:i:sP', $expiresAt),
            'created_at' => gmdate('Y-m-d H:i:sP', $createdAt),
        ]);
    }

    public function consume(string $codeHash, int $now): ?string
    {
        $table = self::TABLE;

        $row = $this->connection->fetchAssociative(
            "UPDATE $table
             SET used_at = to_timestamp(CAST(:now AS double precision))
             WHERE code_hash = :codeHash
                AND used_at IS NULL
                AND expires_at > to_timestamp(CAST(:now AS double precision))
             RETURNING account_id",
            ['now' => $now, 'codeHash' => $codeHash],
        );

        return $row === false ? null : (string) $row['account_id'];
    }
}
