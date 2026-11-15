<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Архивация сессии: сессия остаётся в списке, но новые ходы запрещены.
 *
 * Полноценного удаления сессии нет намеренно: история ходов и диалог остаются
 * доступными для чтения.
 */
readonly class ArhivirovatSessiyu implements JsonBodySerializableInterface
{
    public function __construct(
        public string $sessionId = '',
    ) {
    }
}