<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

/**
 * Привязка ранее загруженных файлов workspace (черновиков) к workspace.
 */
final class PrikrepitFaylyKWorkspace
{
    /**
     * @param string[] $fileIds
     */
    public function __construct(
        public readonly string $workspaceId,
        public readonly array $fileIds,
    ) {
    }
}
