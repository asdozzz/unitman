<?php

declare(strict_types=1);

namespace App\OpenCode\Business\AgentMode\Port;

use App\OpenCode\Business\AgentMode\Model\AgentMode;
use App\OpenCode\Business\AgentMode\Model\NastroykaRezhimaAgenta;

interface AgentModeRepository
{
    public function findById(string $id): ?AgentMode;

    /**
     * Доступные пользователю моды: неархивные общие и его собственные.
     *
     * @return list<AgentMode>
     */
    public function findDostupnye(string $accountId): array;

    /**
     * @return list<AgentMode>
     */
    public function findGlobal(bool $includeArchived = false): array;

    public function findGlobalBySlug(string $slug): ?AgentMode;

    public function findPersonalBySlug(string $accountId, string $slug): ?AgentMode;

    public function save(AgentMode $mode): void;

    public function update(AgentMode $mode): void;

    public function findNastroyka(string $accountId, string $modeId): ?NastroykaRezhimaAgenta;

    /**
     * @return array<string, NastroykaRezhimaAgenta> mode_id => настройки
     */
    public function findNastroyki(string $accountId): array;

    public function saveNastroyka(NastroykaRezhimaAgenta $nastroyka): void;

    /**
     * @return array<string, string> chat_type => mode_id
     */
    public function findDefaulty(string $accountId): array;

    public function saveDefault(string $accountId, string $chatType, string $modeId, int $now): void;
}
