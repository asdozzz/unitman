<?php

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\Account\RefreshToken;
use App\Account\Business\Port\RefreshTokenRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlRefreshTokenRepository implements RefreshTokenRepository
{
    private const TABLE = 'refresh_token';

    public function __construct(private Connection $connection)
    {
    }

    public function save(RefreshToken $refreshToken): void
    {
        $this->connection->insert(
            self::TABLE,
            [
                'token_hash' => $refreshToken->getTokenHash(),
                'account_id' => $refreshToken->getAccountId(),
                'expires_at' => $refreshToken->getExpiresAt(),
                'is_revoked' => $refreshToken->isRevoked(),
            ],
            [
                'token_hash' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'expires_at' => ParameterType::INTEGER,
                'is_revoked' => ParameterType::BOOLEAN,
            ]
        );
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE token_hash = :token_hash",
            ['token_hash' => $tokenHash]
        );

        if (empty($row)) {
            return null;
        }

        return RefreshToken::restore(
            (string) $row['token_hash'],
            (string) $row['account_id'],
            (int) $row['expires_at'],
            $this->toBool($row['is_revoked'])
        );
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $this->connection->update(
            self::TABLE,
            ['is_revoked' => true],
            ['token_hash' => $refreshToken->getTokenHash()],
            ['is_revoked' => ParameterType::BOOLEAN, 'token_hash' => ParameterType::STRING]
        );
    }

    public function revokeAllByAccountId(string $accountId): void
    {
        $this->connection->update(
            self::TABLE,
            ['is_revoked' => true],
            ['account_id' => $accountId],
            ['is_revoked' => ParameterType::BOOLEAN, 'account_id' => ParameterType::STRING]
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 't', 'true'], true);
    }
}
