<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Port;

use App\OpenCode\Business\Model\UchastnikSessii;

/**
 * Участники workspace-сессий на самостоятельном агрегате.
 *
 * Таблица `opencode_workspace_participant` привязана к `workspace_id` внешним
 * ключом на `opencode_workspace_session`. Модель участника переиспользуется:
 * для неё `workspace_id` — это то же, что `sessionId` легаси-агрегата.
 */
interface WorkspaceParticipantsRepository
{
    /**
     * @return list<UchastnikSessii>
     */
    public function findByWorkspaceId(string $workspaceId): array;

    /**
     * Батч-выборка без N+1.
     *
     * @param list<string> $workspaceIds
     *
     * @return array<string, list<UchastnikSessii>> ключ — идентификатор workspace
     */
    public function findByWorkspaceIds(array $workspaceIds): array;

    /**
     * @return list<UchastnikSessii>
     */
    public function findByAccountId(string $accountId): array;

    public function find(string $workspaceId, string $accountId): ?UchastnikSessii;

    public function save(UchastnikSessii $uchastnik): void;

    public function udalit(UchastnikSessii $uchastnik): void;
}
