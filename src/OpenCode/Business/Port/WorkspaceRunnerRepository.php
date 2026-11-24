<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

/**
 * Жёсткая привязка workspace к раннеру (multi-runner).
 *
 * Контракт: plans/runner-multi-runner.md, §5.2 и §7. Workspace никогда не
 * переносится автоматически: повторный `bind` не меняет существующую привязку.
 */
interface WorkspaceRunnerRepository
{
    public function findRunnerIdByWorkspaceId(string $workspaceId): ?string;

    /**
     * Идемпотентная привязка. Если workspace уже привязан к другому раннеру,
     * привязка не меняется (жёсткая привязка).
     */
    public function bind(string $workspaceId, string $runnerId): void;

    public function unbind(string $workspaceId): void;

    public function isBoundToRunner(string $workspaceId, string $runnerId): bool;

    public function countByRunnerId(string $runnerId): int;
}
