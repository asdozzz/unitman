<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Проект в составе workspace-стенда.
 *
 * `StorageUrl` содержит токен доступа и передаётся только в Go-runner.
 */
final class ProektWorkspaceCommand
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $ProjectCode,
        public readonly string $StorageUrl,
        public readonly bool $CanPush,
        public readonly string $MainBranch,
    ) {
    }
}
