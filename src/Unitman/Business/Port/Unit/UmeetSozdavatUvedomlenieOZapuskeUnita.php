<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

/**
 * Уведомление автора об успешном запуске юнита.
 */
interface UmeetSozdavatUvedomlenieOZapuskeUnita
{
    /**
     * @param array<string, mixed> $payload
     */
    public function sozdat(string $accountId, array $payload, string $dedupKey): void;
}
