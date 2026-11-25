<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Уровень важности уведомления.
 *
 * Прочтение требуется только для `attention` и `critical`: информационные
 * уведомления не должны засорять бейдж непрочитанного.
 */
enum UrovenVazhnosti: string
{
    case INFO = 'info';
    case ATTENTION = 'attention';
    case CRITICAL = 'critical';

    public function trebueProchteniya(): bool
    {
        return $this !== self::INFO;
    }
}
