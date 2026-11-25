<?php

declare(strict_types=1);

namespace App\Notification\Business\Command;

use App\Notification\Business\Model\TipUvedomleniya;

/**
 * Изменение персональной настройки типа уведомления.
 */
final class IzmenitNastroikuUvedomleniya
{
    public function __construct(
        public readonly TipUvedomleniya $type,
        public readonly bool $enabled,
    ) {
    }
}
