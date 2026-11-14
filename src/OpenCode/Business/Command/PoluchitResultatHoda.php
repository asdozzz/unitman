<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Опрос результата хода.
 *
 * Клиент вызывает эндпоинт повторно, пока `status` не станет терминальным:
 * `ANSWERED`, `AWAITING_INPUT` или `FAILED`. Пока ход в `RUNNING`, результат
 * ещё не получен, но запрос не ошибка — фронтенд продолжает опрос.
 */
readonly class PoluchitResultatHoda implements JsonBodySerializableInterface
{
    public function __construct(
        public string $turnId = '',
    ) {
    }
}
