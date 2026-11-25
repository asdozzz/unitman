<?php

declare(strict_types=1);

namespace App\Unitman\Acl;

use App\Notification\Api\NotificationApi;
use App\Unitman\Business\Port\Unit\UmeetSozdavatUvedomlenieOZapuskeUnita;

/**
 * Антикоррупционный слой между Unitman и Notification.
 */
final class NotificationAdapter implements UmeetSozdavatUvedomlenieOZapuskeUnita
{
    public function __construct(private readonly NotificationApi $notificationApi)
    {
    }

    public function sozdat(string $accountId, array $payload, string $dedupKey): void
    {
        $this->notificationApi->sozdatUvedomlenieOZapuskeUnita($accountId, $payload, $dedupKey);
    }
}
