<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Непрочитанные уведомления вместе с именем персонального канала.
 *
 * Форма `toArray()` — HTTP-контракт раздела 6.1: клиент получает готовое
 * имя канала и не конструирует его самостоятельно.
 */
final class UvedomleniyaOpenCodeReadModel
{
    /**
     * @param list<UvedomlenieONovomSoobsheniiOpenCode> $notifications
     */
    public function __construct(
        public readonly string $channel,
        public readonly array $notifications,
    ) {
    }

    /**
     * @return array{channel: string, notifications: list<array<string, string|int|null>>}
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'notifications' => array_map(
                static fn (UvedomlenieONovomSoobsheniiOpenCode $notification): array => $notification->toArray(),
                $this->notifications,
            ),
        ];
    }
}
