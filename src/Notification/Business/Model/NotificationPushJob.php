<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Задание RoadRunner Jobs на отправку push по одному уведомлению.
 */
final class NotificationPushJob
{
    public function __construct(
        public readonly string $notificationId = '',
    ) {
    }
}
