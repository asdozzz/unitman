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
     * @param list<string>          $projectIds
     * @param string                $branch          общая ветка workspace
     * @param array<string, string> $projectBranches переопределения ветки по projectId
     *
     * @return list<ProektWorkspace>
     *
     * @throws \DomainException opencode.workspace.project_conflict|opencode.workspace.project_not_found|opencode.workspace.branch_protected
     */
    public function poluchitProekti(
        array $projectIds,
        string $accountId,
        string $branch = '',
        array $projectBranches = [],
    ): array;

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

    /**
     * Ссылка на произвольный порт workspace-контейнера.
     *
     * Workspace не привязан к одному проекту, поэтому `proxyHost` берётся у
     * первого проекта, а псевдо-проект — `workspaces`. Формула совпадает с
     * юнитной: `{scheme}://{port}.{workspaceSlug}.workspaces.{host}[:{port}]`.
     * Для `ws`/`wss` схема выбирается по схеме proxy (`https` → `wss`).
     *
     * Возвращает null, если proxy host не задан или ссылку построить нельзя.
     */
    public function poluchitSsylkuPoPortu(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
        int $port,
        ?string $type = null,
        ?string $suffix = null,
    ): ?string;
}
