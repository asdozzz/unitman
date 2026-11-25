<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

/**
 * Создание уведомления администраторам об угрозе раннера.
 */
interface UmeetSozdavatUvedomlenieOBugroze
{
    /**
     * @param array<string, mixed> $payload
     */
    public function sozdat(string $runnerId, string $level, array $payload, string $dedupKey): void;
}
