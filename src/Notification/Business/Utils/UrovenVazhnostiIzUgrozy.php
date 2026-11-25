<?php

declare(strict_types=1);

namespace App\Notification\Business\Utils;

use App\Notification\Business\Model\UrovenVazhnosti;

/**
 * Маппинг уровня угрозы раннера в важность уведомления.
 *
 * `warning` и `ok` уведомлений не создают. `critical` и `blocker` дают
 * критичное уведомление.
 */
final class UrovenVazhnostiIzUgrozy
{
    public static function dlyaUrovnyaRannera(string $level): ?UrovenVazhnosti
    {
        return match (strtolower(trim($level))) {
            'critical', 'blocker' => UrovenVazhnosti::CRITICAL,
            default => null,
        };
    }

    public static function kriticheskiy(string $level): bool
    {
        return self::dlyaUrovnyaRannera($level) !== null;
    }
}
