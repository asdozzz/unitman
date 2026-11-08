<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit\PeremennayaSvyaz;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;

/**
 * Проверка связей переменных с юнитами, выбранными через хелпер.
 *
 * Связь валидна, если:
 * - выбранный юнит существует;
 * - выбранный юнит лежит в проекте, доступном пользователю;
 * - выбранный юнит не совпадает с юнитом-источником.
 *
 * Инвариант «одна связь на переменную» проверяет агрегат
 * (`Unit::makeSvyaziCollection()`), здесь он не дублируется.
 */
final class ProverkaSvyazeyPeremenih
{
    public function __construct(
        private CanGetUnitReadModelById $canGetUnitReadModelById,
    )
    {
    }

    /**
     * @param PeremennayaSvyaz[] $svyazi
     * @param string|null $unitId юнит-источник; `null` при создании юнита,
     *                            когда id ещё не известен (проверка self-link не нужна)
     * @param string[] $projectIds проекты, доступные текущему пользователю
     */
    public function proverit(array $svyazi, ?string $unitId, array $projectIds): void
    {
        if ($svyazi === []) {
            return;
        }

        $projectIdSet = array_fill_keys($projectIds, true);

        foreach ($svyazi as $svyaz) {
            $targetUnitId = $svyaz->getTargetUnitId();

            if ($unitId !== null && $targetUnitId === $unitId) {
                throw new \DomainException('unit.svyaz.self_link_is_forbidden');
            }

            if (!isset($projectIdSet[$this->canGetUnitReadModelById->getById($targetUnitId)->projectId])) {
                throw new \DomainException('unit.svyaz.target_unit_project_is_not_available');
            }
        }
    }
}
