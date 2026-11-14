<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Новый промпт в существующей сессии.
 *
 * Если предыдущий ход сессии ждёт ответа на вопрос, промпт отклоняется:
 * сначала нужно ответить через `/api/opencode/otvetit`.
 */
readonly class NachatPrompt implements JsonBodySerializableInterface
{
    public function __construct(
        public string $sessionId = '',
        public string $text = '',
    ) {
    }
}