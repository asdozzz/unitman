<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Архивация workspace.
 *
 * Без `force` сервер сначала проверяет незакоммиченные изменения в проектах
 * и требует явного подтверждения. С `force` проверка пропускается.
 */
readonly class ArhivirovatWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public string $workspaceId = '',
        public bool $force = false,
    ) {
    }
}
