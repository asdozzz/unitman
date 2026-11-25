<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

use App\Notification\Business\Model\Dostavka;

interface DostavkiRepository
{
    /**
     * Атомарно захватывает доставки к отправке и возвращает их notificationId.
     *
     * @return list<string>
     */
    public function zahvatitNaOtpravku(string $channel, int $limit, int $leaseSeconds, int $now): array;

    public function find(string $notificationId, string $channel): ?Dostavka;

    public function markSent(string $notificationId, string $channel, int $now): void;

    public function markPending(string $notificationId, string $channel, string $error): void;

    public function markFailed(
        string $notificationId,
        string $channel,
        int $now,
        string $error,
        int $maxAttempts,
    ): void;
}
