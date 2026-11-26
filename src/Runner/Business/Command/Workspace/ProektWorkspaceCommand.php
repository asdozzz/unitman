<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Проект в составе workspace-стенда.
 *
 * `StorageUrl` — чистый remote URL без userinfo. `RepoId` нужен Go-runner
 * для just-in-time запроса короткоживущего гранта; токен в команде не
 * передаётся.
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
        public readonly string $RepoId = '',
    ) {
    }
}
