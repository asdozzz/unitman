<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Файл workspace для применения в контейнере.
 *
 * `Url` — короткоживущая подписанная ссылка; в БД она не сохраняется.
 */
final class WorkspaceFileCommand
{
    public function __construct(
        public readonly string $FileId,
        public readonly string $Name,
        public readonly string $Url,
    ) {
    }
}
