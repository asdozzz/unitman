<?php

declare(strict_types=1);

namespace App\Notification\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Отписка браузера от Web Push.
 */
final class OtpisatsyaOtPush implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $endpoint = '',
    ) {
    }
}
