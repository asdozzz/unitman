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
     * Есть ли у аккаунта доступ ко всем перечисленным проектам.
     *
     * Нужна для приглашения в workspace: общий чат и read-only IDE нельзя
     * открывать участнику без прав на проекты, иначе он увидит чужой код.
     *
     * @param list<string> $projectIds
     */
    public function estDostupKProektam(array $projectIds, string $accountId): bool;

    /**
     * Ссылка на редактор workspace (VS Code) или null, если её нельзя построить.
     *
     * Формула использует публичный адрес (`publicAddress`) раннера, к которому
     * привязан workspace, и порт IDE; токен клонирования наружу не отдаётся.
     */
    public function poluchitSsylkuRedaktora(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
    ): ?string;

    /**
     * Ссылка на произвольный порт workspace-контейнера.
     *
     * Workspace не привязан к одному проекту: `publicAddress` берётся у
     * раннера, к которому привязан workspace, а псевдо-проект — `workspaces`.
     * Формула совпадает с юнитной:
     * `{scheme}://{port}.{workspaceSlug}.workspaces.{host}[:{publicPort}]`.
     * Для `ws`/`wss` схема выбирается по схеме адреса (`https` → `wss`).
     *
     * Возвращает null, если публичный адрес не задан или ссылку построить
     * нельзя.
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
