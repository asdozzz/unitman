<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Фильтры списка сессий текущего пользователя.
 *
 * `onlyActive = false` по умолчанию — показываем все сессии, включая архивные.
 * `search` — подстрока названия сессии, ищется через `LIKE`.
 *
 * Команда передаётся в query из query string GET-запроса, поэтому не реализует
 * `JsonBodySerializableInterface`.
 */
readonly class PoluchitSpisokSessiy
{
    public function __construct(
        public bool $onlyActive = false,
        public ?string $search = null,
    ) {
    }
}