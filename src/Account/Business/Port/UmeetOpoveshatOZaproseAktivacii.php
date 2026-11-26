<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

/**
 * Уведомление администраторов о новой заявке на активацию аккаунта.
 */
interface UmeetOpoveshatOZaproseAktivacii
{
    public function zaproshena(string $accountId, string $email, ?string $nickname): void;
}
