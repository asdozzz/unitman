<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\ProektWorkspace;

/**
 * Доступ к проектам Unitman для workspace-сессии.
 *
 * Реализация — ACL-адаптер над публичным `UnitmanApi`: модуль OpenCode не
 * знает про внутренние read-модели Unitman и не получает токенизированный URL
 * клонирования.
 */
interface UmeetUpravlyatProektamiWorkspace
{
    /**
     * @param list<string> $projectIds
     *
     * @return list<ProektWorkspace>
     *
     * @throws \DomainException opencode.workspace.project_conflict|opencode.workspace.project_not_found
     */
    public function poluchitProekti(array $projectIds, string $accountId): array;

    /**
     * Ссылка на редактор workspace (VS Code) или null, если её нельзя построить.
     *
     * Формула использует `proxyHost` первого проекта и порт IDE; токен клонирования
     * наружу не отдаётся.
     */
    public function poluchitSsylkuRedaktora(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
    ): ?string;
}
