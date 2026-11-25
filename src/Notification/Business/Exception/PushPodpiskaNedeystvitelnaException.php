<?php

declare(strict_types=1);

namespace App\Notification\Business\Exception;

/**
 * Push-сервис ответил 404/410: подписку нужно удалить.
 */
final class PushPodpiskaNedeystvitelnaException extends \RuntimeException
{
}
