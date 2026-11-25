<?php

declare(strict_types=1);

namespace App\AiChat\Business\ReadModel;

/**
 * Страница списка сессий AI-чата вместе с метаданными пагинации.
 *
 * `items` — сессии текущего пользователя, у которых есть хотя бы одно
 * сообщение; в каждой карточке ровно один `preview` первого сообщения.
 * `total` — общее количество подходящих сессий без учёта `limit`/`offset`,
 * `hasMore` — есть ли ещё записи после текущей страницы.
 */
final class SpisokSessiyAiChataReadModel
{
    /**
     * @param list<SessiyaAiChataReadModel> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $limit,
        public readonly int $offset,
        public readonly int $total,
    ) {
    }

    public function hasMore(): bool
    {
        return $this->offset + \count($this->items) < $this->total;
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     limit: int,
     *     offset: int,
     *     total: int,
     *     hasMore: bool
     * }
     */
    public function vMassiv(): array
    {
        return [
            'items' => array_map(
                static fn (SessiyaAiChataReadModel $sessiya): array => $sessiya->vMassiv(),
                $this->items,
            ),
            'limit' => $this->limit,
            'offset' => $this->offset,
            'total' => $this->total,
            'hasMore' => $this->hasMore(),
        ];
    }
}
