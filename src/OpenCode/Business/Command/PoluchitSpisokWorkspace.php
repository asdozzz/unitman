<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Фильтры списка workspace-сессий текущего пользователя.
 *
 * Команда собирается из query string GET-запроса, поэтому не реализует
 * `JsonBodySerializableInterface`.
 */
readonly class PoluchitSpisokWorkspace
{
    public function __construct(
        public bool $onlyActive = false,
        public ?string $search = null,
    ) {
    }
}
