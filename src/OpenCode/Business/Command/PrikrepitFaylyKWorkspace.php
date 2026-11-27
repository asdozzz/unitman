<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Прикрепление ранее загруженных файлов к workspace.
 */
readonly class PrikrepitFaylyKWorkspace implements JsonBodySerializableInterface
{
    /**
     * @param list<string> $fileIds
     */
    public function __construct(
        public string $workspaceId = '',
        public array $fileIds = [],
    ) {
    }
}
