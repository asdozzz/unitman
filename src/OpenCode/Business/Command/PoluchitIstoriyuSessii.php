<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос всех ходов сессии в порядке `seq`.
 *
 * История отдаётся целиком: она же служит контекстом для следующего хода,
 * поэтому фронтенд и бэкенд видят один и тот же набор реплик.
 */
readonly class PoluchitIstoriyuSessii implements JsonBodySerializableInterface
{
    public function __construct(
        public string $sessionId = '',
    ) {
    }
}
