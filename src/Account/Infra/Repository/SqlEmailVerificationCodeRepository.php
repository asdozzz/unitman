<?php

declare(strict_types=1);

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\EmailVerification\EmailVerificationCode;
use App\Account\Business\Port\EmailVerificationCodeRepository;
use Doctrine\DBAL\Connection;

final class SqlEmailVerificationCodeRepository implements EmailVerificationCodeRepository
{
    private const TABLE = 'account_email_verification';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByEmail(string $email): ?EmailVerificationCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table
             WHERE email = :email AND consumed_at IS NULL
             ORDER BY last_sent_at DESC
             LIMIT 1",
            ['email' => $email],
        );

        return $row === false ? null : $this->makeCode($row);
    }

    public function findByAccountId(string $accountId): ?EmailVerificationCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE account_id = :accountId",
            ['accountId' => $accountId],
        );

        return $row === false ? null : $this->makeCode($row);
    }

    public function save(EmailVerificationCode $code): void
    {
        $table = self::TABLE;

        $this->connection->executeStatement(
            "INSERT INTO $table
                (account_id, email, code_hash, expires_at, attempts, sent_count, last_sent_at, consumed_at)
             VALUES
                (:accountId, :email, :codeHash, to_timestamp(CAST(:expiresAt AS double precision)), :attempts, :sentCount, to_timestamp(CAST(:lastSentAt AS double precision)), to_timestamp(CAST(:consumedAt AS double precision)))
             ON CONFLICT (account_id) DO UPDATE SET
                email = EXCLUDED.email,
                code_hash = EXCLUDED.code_hash,
                expires_at = EXCLUDED.expires_at,
                attempts = EXCLUDED.attempts,
                sent_count = EXCLUDED.sent_count,
                last_sent_at = EXCLUDED.last_sent_at,
                consumed_at = EXCLUDED.consumed_at",
            [
                'accountId' => $code->accountId,
                'email' => $code->email,
                'codeHash' => $code->codeHash,
                'expiresAt' => $code->expiresAt,
                'attempts' => $code->attempts,
                'sentCount' => $code->sentCount,
                'lastSentAt' => $code->lastSentAt,
                'consumedAt' => $code->consumedAt,
            ],
        );
    }

    public function consume(EmailVerificationCode $code, int $now): void
    {
        $table = self::TABLE;

        $this->connection->executeStatement(
            "UPDATE $table SET consumed_at = to_timestamp(CAST(:now AS double precision)) WHERE account_id = :accountId",
            ['now' => $now, 'accountId' => $code->accountId],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeCode(array $row): EmailVerificationCode
    {
        return new EmailVerificationCode(
            (string) $row['account_id'],
            (string) $row['email'],
            (string) $row['code_hash'],
            (int) strtotime((string) $row['expires_at']),
            (int) $row['attempts'],
            (int) $row['sent_count'],
            (int) strtotime((string) $row['last_sent_at']),
            $row['consumed_at'] !== null ? (int) strtotime((string) $row['consumed_at']) : null,
        );
    }
}
