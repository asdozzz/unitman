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
     * Список «мои + где я участник» без N+1: участие учитывается join-ом.
     *
     * @return list<WorkspaceSession>
     */
    public function findByAccountIdWithParticipation(string $accountId, WorkspaceFilter $filter): array;

    /**
     * @return list<WorkspaceSession>
     */
    public function findAktivnie(int $limit): array;

    /**
     * Активные и архивные в транзитных статусах — как легаси
     * `findAktivnieWorkspace()`. Нужно фоновой сверке операций.
     *
     * @return list<WorkspaceSession>
     */
    public function findAktivnieVTranzite(int $limit): array;

    /**
     * @param list<StatusWorkspace> $from
     */
    public function perevestiStatus(
        string $workspaceId,
        array $from,
        StatusWorkspace $to,
    ): bool;

    public function delete(string $workspaceId): void;

    /**
     * Идемпотентно сохраняет pending-обновление workspace.
     *
     * @param array<string, mixed> $payload `WorkspacePendingUpdate::toArray()`
     */
    public function savePendingUpdate(string $workspaceId, string $workflowId, array $payload, int $createdAt): void;

    /**
     * @return array{workflowId: string, payload: array<string, mixed>, createdAt: int}|null
     */
    public function findPendingUpdate(string $workspaceId): ?array;

    public function deletePendingUpdate(string $workspaceId): void;

    /**
     * Батч-признак «есть pending-обновление» без N+1.
     *
     * @param list<string> $workspaceIds
     *
     * @return array<string, true> ключ — идентификатор workspace
     */
    public function findPendingWorkspaceIds(array $workspaceIds): array;
}
