<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Состояние доставки уведомления по каналу.
 */
enum StatusDostavki: string
{
    case PENDING = 'pending';
    case DISPATCHED = 'dispatched';
    case SENT = 'sent';
    case FAILED = 'failed';
}
