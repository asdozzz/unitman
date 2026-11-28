<?php

declare(strict_types=1);

namespace App\Skill\Business\Port;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Model\VersiyaNavyka;

/**
 * Хранилище навыков OpenCode.
 *
 * Привязка к проектам не имеет FK на проекты: проекты живут в event-sourcing
 * и удаляются асинхронно. Репозиторий не проверяет существование проекта.
 */
interface NavykRepository
{
    /**
     * Создаёт навык, его первую версию и привязки к проектам в одной
     * транзакции. Для глобального навыка проверяет уникальность slug среди
     * неархивных глобальных навыков.
     */
    public function create(NavykOpenCode $navyk): void;

    /**
     * Обновляет навык. Если версия выросла, добавляет строку в историю
     * версий (в той же транзакции).
     */
    public function update(NavykOpenCode $navyk): void;

    /**
     * Мягкое удаление: проставляет `archived_at`. История и привязки
     * сохраняются.
     */
    public function archive(NavykOpenCode $navyk): void;

    public function findById(string $id): ?NavykOpenCode;

    /**
     * @param list<string> $ids
     *
     * @return list<NavykOpenCode>
     */
    public function findByIds(array $ids): array;

    /**
     * Глобальные навыки. По умолчанию — только неархивные.
     *
     * @return list<NavykOpenCode>
     */
    public function listGlobal(bool $includeArchived = false): array;

    /**
     * Проектные навыки, привязанные хотя бы к одному из проектов.
     * По умолчанию — только неархивные.
     *
     * @param list<string> $projectIds
     *
     * @return list<NavykOpenCode>
     */
    public function listForProjects(array $projectIds, bool $includeArchived = false): array;

    /**
     * @param list<string> $projectIds
     */
    public function attachToProjects(string $skillId, array $projectIds, string $addedBy, int $now): void;

    public function detachFromProject(string $skillId, string $projectId): void;

    /**
     * История версий от новой к старой.
     *
     * @return list<VersiyaNavyka>
     */
    public function listVersions(string $skillId): array;

    /**
     * Эффективный набор навыков для каждого проекта: глобальные + привязанные
     * к этому проекту. Ключ — project_id.
     *
     * @param list<string> $projectIds
     *
     * @return array<string, list<NavykOpenCode>>
     */
    public function resolveEffectiveForProjects(array $projectIds): array;

    /**
     * Проверка уникальности глобального slug в коде (частичный уникальный
     * индекс не используется из-за переносимости БД).
     */
    public function isGlobalSlugTaken(string $slug, ?string $excludeSkillId = null): bool;

    public function findGlobalBySlug(string $slug): ?NavykOpenCode;
}
