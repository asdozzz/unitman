<?php

declare(strict_types=1);

namespace App\Runner\Acl;

use App\Notification\Api\NotificationApi;
use App\Runner\Business\Port\UmeetSozdavatUvedomlenieOBugroze;

/**
 * Антикоррупционный слой между Runner и Notification.
 */
final class NotificationAdapter implements UmeetSozdavatUvedomlenieOBugroze
{
    public function __construct(private readonly NotificationApi $notificationApi)
    {
    }

    public function sozdat(string $runnerId, string $level, array $payload, string $dedupKey): void
    {
        $this->notificationApi->sozdatUvedomlenieOBugrozeRannera($runnerId, $level, $payload, $dedupKey);
    }
}
