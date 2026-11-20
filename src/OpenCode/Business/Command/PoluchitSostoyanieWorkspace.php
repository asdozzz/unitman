<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Состояние workspace для интерфейса: статус, причина блокировки, последний ход.
 */
readonly class PoluchitSostoyanieWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public string $workspaceId = '',
    ) {
    }
}
