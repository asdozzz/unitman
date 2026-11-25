<?php

declare(strict_types=1);

namespace App\AiChat\Business\Model;

/**
 * Фильтр списка AI-сессий пользователя.
 *
 * `limit` ограничен диапазоном 1..100, `offset` не может быть отрицательным.
 * Пустой `search` нормализуется в `null`.
 */
final class FiltrSessiyAiChata
{
    public const MIN_LIMIT = 1;
    public const MAX_LIMIT = 100;

    public readonly bool $includeArchived;
    public readonly ?string $search;
    public readonly int $limit;
    public readonly int $offset;

    public function __construct(
        bool $includeArchived = false,
        ?string $search = null,
        int $limit = 50,
        int $offset = 0,
    ) {
        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw new \DomainException('ai.chat.invalid_limit');
        }

        if ($offset < 0) {
            throw new \DomainException('ai.chat.invalid_offset');
        }

        $normalizedSearch = $search !== null ? trim($search) : null;

        $this->includeArchived = $includeArchived;
        $this->search = $normalizedSearch !== '' ? $normalizedSearch : null;
        $this->limit = $limit;
        $this->offset = $offset;
    }
}
