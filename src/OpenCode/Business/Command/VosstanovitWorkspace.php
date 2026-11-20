<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Восстановление архивного workspace.
 */
readonly class VosstanovitWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public string $workspaceId = '',
    ) {
    }
}
