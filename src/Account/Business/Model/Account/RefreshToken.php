<?php

namespace App\Account\Business\Model\Account;

/**
 * Refresh-токен аккаунта.
 *
 * Хранится только хеш значения, поэтому по записи из БД исходный токен не восстановить.
 * Токен одноразовый: при обновлении пары токенов он отзывается и выпускается новый.
 * */
final class RefreshToken
{
    private function __construct(
        private readonly string $tokenHash,
        private readonly string $accountId,
        private readonly int $expiresAt,
        private bool $isRevoked,
    )
    {
    }

    public static function issue(string $tokenHash, string $accountId, int $expiresAt): self
    {
        if ($tokenHash === '') {
            throw new \DomainException('account.refresh_token.empty_hash');
        }

        if ($accountId === '') {
            throw new \DomainException('account.refresh_token.empty_account_id');
        }

        if ($expiresAt <= 0) {
            throw new \DomainException('account.refresh_token.invalid_expires_at');
        }

        return new self($tokenHash, $accountId, $expiresAt, false);
    }

    public static function restore(string $tokenHash, string $accountId, int $expiresAt, bool $isRevoked): self
    {
        return new self($tokenHash, $accountId, $expiresAt, $isRevoked);
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function revoke(): void
    {
        if ($this->isRevoked) {
            throw new \DomainException('account.refresh_token.already_revoked');
        }

        $this->isRevoked = true;
    }

    public function isExpired(int $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function assertCanBeUsed(int $now): void
    {
        if ($this->isRevoked) {
            throw new \DomainException('account.refresh_token.revoked');
        }

        if ($this->isExpired($now)) {
            throw new \DomainException('account.refresh_token.expired');
        }
    }
}
