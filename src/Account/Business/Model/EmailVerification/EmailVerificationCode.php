<?php

declare(strict_types=1);

namespace App\Account\Business\Model\EmailVerification;

/**
 * Краткоживущая запись о коде подтверждения email.
 *
 * Хранится вне event store: это не бизнес-инвариант аккаунта, а секрет с
 * ограниченным сроком жизни и счётчиком попыток.
 */
final class EmailVerificationCode
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $email,
        public readonly string $codeHash,
        public readonly int $expiresAt,
        public readonly int $attempts = 0,
        public readonly int $sentCount = 1,
        public readonly int $lastSentAt = 0,
        public readonly ?int $consumedAt = null,
    ) {
    }

    public function isExpired(int $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function withNewCode(
        string $codeHash,
        int $expiresAt,
        int $lastSentAt,
        int $sentCount,
    ): self {
        return new self(
            $this->accountId,
            $this->email,
            $codeHash,
            $expiresAt,
            attempts: 0,
            sentCount: $sentCount,
            lastSentAt: $lastSentAt,
        );
    }

    public function withIncrementedAttempts(): self
    {
        return new self(
            $this->accountId,
            $this->email,
            $this->codeHash,
            $this->expiresAt,
            attempts: $this->attempts + 1,
            sentCount: $this->sentCount,
            lastSentAt: $this->lastSentAt,
            consumedAt: $this->consumedAt,
        );
    }

    public function consumed(int $now): self
    {
        return new self(
            $this->accountId,
            $this->email,
            $this->codeHash,
            $this->expiresAt,
            attempts: $this->attempts,
            sentCount: $this->sentCount,
            lastSentAt: $this->lastSentAt,
            consumedAt: $now,
        );
    }
}
