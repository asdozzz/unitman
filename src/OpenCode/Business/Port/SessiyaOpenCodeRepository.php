<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;

interface SessiyaOpenCodeRepository
{
    public function save(SessiyaOpenCode $session): void;

    public function update(SessiyaOpenCode $session): void;

    /**
     * @throws \DomainException если сессия не найдена
     */
    public function getById(string $id): SessiyaOpenCode;

    public function findById(string $id): ?SessiyaOpenCode;

    /**
     * @return list<SessiyaOpenCode>
     */
    public function findByAccountId(string $accountId, SessiyaOpenCodeFilter $filter): array;

    /**
     * @return list<SessiyaOpenCode>
     */
    public function findByCredentialId(string $credentialId): array;

    /**
     * Активные (неархивные) сессии хотя бы с одним юнитом — вход для фоновой сверки.
     *
     * @return list<SessiyaOpenCode>
     */
    public function findAktivnieSUnitom(int $limit): array;

    /**
     * Workspace-сессии — вход для фоновой сверки операций.
     *
     * Помимо неархивных сессий сюда попадают архивные, у которых workspace
     * в транзитном статусе: удаление архива и восстановление из архива тоже
     * должны досверяться (иначе они навсегда залипают в `DELETING`/`STARTING`).
     *
     * @return list<SessiyaOpenCode>
     */
    public function findAktivnieWorkspace(int $limit): array;

    /**
     * Атомарный переход статуса workspace. Возвращает true, если строка была обновлена.
     *
     * @param list<StatusWorkspace> $from
     */
    public function perevestiStatusWorkspace(
        string $sessionId,
        array $from,
        StatusWorkspace $to,
    ): bool;

    public function saveWorkspaceUpdate(
        string $sessionId,
        string $workflowId,
        WorkspacePendingUpdate $update,
    ): void;

    /**
     * @return array{workflowId: string, update: WorkspacePendingUpdate}|null
     */
    public function findWorkspaceUpdate(string $sessionId): ?array;

    public function deleteWorkspaceUpdate(string $sessionId): void;

    /**
     * Полное удаление workspace: проекты, workspace, ходы, уведомления, сессия.
     */
    public function deleteWorkspace(string $sessionId): void;
}
