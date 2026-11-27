<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос подписанной ссылки на файл-артефакт хода.
 */
final class PoluchitSsylkuFajlaHoda implements JsonBodySerializableInterface
{
    public function __construct(
        public string $turnId = '',
        public string $fileId = '',
    ) {
    }
}
