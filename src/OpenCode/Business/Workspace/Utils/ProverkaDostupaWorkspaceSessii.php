<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Utils;

use App\OpenCode\Business\Workspace\Model\WorkspaceSession;

/**
 * Доступ к workspace имеет только владелец: редакторы юнитов сюда не входят.
 */
final class ProverkaDostupaWorkspaceSessii
{
    public function proverit(WorkspaceSession $workspace, string $accountId): void
    {
        if (!$workspace->prinadlezhit($accountId)) {
            throw new \DomainException('opencode.workspace.not_found');
        }
    }
}
