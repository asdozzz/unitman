<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Привязка уже существующей сессии к юнитам.
 *
 * Привязка дополняет список юнитов сессии: первый добавленный юнит задаёт
 * рабочий каталог хода, последующие — дополнительные корни workspace (multi-root).
 * Повторная привязка того же юнита ничего не меняет.
 */
readonly class PrivezatSessiyuKUnitu implements JsonBodySerializableInterface
{
    /**
     * @param list<string> $unitIds
     */
    public function __construct(
        public string $sessionId = '',
        public array $unitIds = [],
    ) {
    }
}
