<?php

declare(strict_types=1);

namespace App\Notification\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Подписка браузера на Web Push.
 */
final class PodpisatsyaNaPush implements JsonBodySerializableInterface
{
    /**
     * @param array<string, string> $keys
     */
    public function __construct(
        public readonly string $endpoint = '',
        public readonly array $keys = [],
        public readonly string $locale = 'ru',
    ) {
    }

    public function p256dh(): string
    {
        return $this->keys['p256dh'] ?? '';
    }

    public function auth(): string
    {
        return $this->keys['auth'] ?? '';
    }
}
