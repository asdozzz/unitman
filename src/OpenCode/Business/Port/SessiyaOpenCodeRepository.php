<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\StatusWorkspace;

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
     * Активные workspace-сессии — вход для фоновой сверки операций.
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

    /**
     * Полное удаление workspace: проекты, workspace, ходы, уведомления, сессия.
     */
    public function deleteWorkspace(string $sessionId): void;
}
