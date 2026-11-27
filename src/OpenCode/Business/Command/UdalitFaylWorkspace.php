<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Удаление файла workspace.
 */
readonly class UdalitFaylWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public string $workspaceId = '',
        public string $fileId = '',
    ) {
    }
}
