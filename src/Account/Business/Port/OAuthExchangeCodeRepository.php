<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

interface OAuthExchangeCodeRepository
{
    public function save(string $codeHash, string $accountId, int $expiresAt, int $createdAt): void;

    /**
     * Атомарно помечает код использованным и возвращает accountId.
     */
    public function consume(string $codeHash, int $now): ?string;
}
