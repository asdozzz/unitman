<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос текста файла-артефакта хода для preview.
 */
final class PoluchitTekstFajlaHoda implements JsonBodySerializableInterface
{
    public function __construct(
        public string $turnId = '',
        public string $fileId = '',
    ) {
    }
}
