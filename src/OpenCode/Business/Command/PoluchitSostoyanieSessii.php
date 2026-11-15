<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Состояние сессии для интерфейса: причина блокировки ходов.
 */
readonly class PoluchitSostoyanieSessii implements JsonBodySerializableInterface
{
    public function __construct(
        public string $sessionId = '',
    ) {
    }
}