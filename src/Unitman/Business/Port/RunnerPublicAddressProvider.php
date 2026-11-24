<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port;

/**
 * Публичный адрес раннера по его `runnerId` (multi-runner).
 *
 * Контракт v2: ссылки строятся только по `publicAddress`; устаревшие
 * источники адреса не используются. Default/fallback host запрещён: если
 * адрес неизвестен — возвращается `null`.
 *
 * Контракт: plans/runner-multi-runner.md, §1 и §7.
 */
interface RunnerPublicAddressProvider
{
    public function getPublicAddress(string $runnerId): ?string;
}
