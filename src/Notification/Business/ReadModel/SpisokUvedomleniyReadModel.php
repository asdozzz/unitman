<?php

declare(strict_types=1);

namespace App\Notification\Business\ReadModel;

/**
 * Список уведомлений текущего пользователя и счётчик непрочитанных.
 */
final class SpisokUvedomleniyReadModel
{
    /**
     * @param list<UvedomlenieReadModel> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $unreadCount,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (UvedomlenieReadModel $item): array => $item->toArray(),
                $this->items,
            ),
            'unreadCount' => $this->unreadCount,
        ];
    }
}
