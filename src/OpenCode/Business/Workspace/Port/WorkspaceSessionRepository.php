<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Port;

use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFilter;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;

interface WorkspaceSessionRepository
{
    public function save(WorkspaceSession $workspace): void;

    public function update(WorkspaceSession $workspace): void;

    /**
     * @throws \DomainException если workspace не найден
     */
    public function getById(string $id): WorkspaceSession;

    public function findById(string $id): ?WorkspaceSession;

    /**
     * @return list<WorkspaceSession>
     */
    public function findByAccountId(string $accountId, WorkspaceFilter $filter): array;

    /**
     * @return list<WorkspaceSession>
     */
    public function findAktivnie(int $limit): array;

    /**
     * @param list<StatusWorkspace> $from
     */
    public function perevestiStatus(
        string $workspaceId,
        array $from,
        StatusWorkspace $to,
    ): bool;

    public function delete(string $workspaceId): void;
}
