<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Запрос непрочитанных уведомлений текущего аккаунта.
 *
 * Команда передаётся из query string GET-запроса, поэтому не реализует
 * `JsonBodySerializableInterface`.
 */
readonly class PoluchitUvedomleniyaOpenCode
{
    public const DEFAULT_LIMIT = 50;

    public const MAX_LIMIT = 100;

    public function __construct(
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }
}
