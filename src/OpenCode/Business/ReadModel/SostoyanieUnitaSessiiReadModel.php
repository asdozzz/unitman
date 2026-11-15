<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Состояние одного юнита сессии для интерфейса.
 *
 * `deleted` — юнит удалён в Unitman (`group === archived`); такие юниты гейт
 * хода молча пропускает, а сессия архивируется только когда удалены все.
 * `ready` — юнит в группе `ready`, то есть не блокирует сессию.
 */
final class SostoyanieUnitaSessiiReadModel
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $unitProjectId,
        public readonly ?string $unitStateCode,
        public readonly string $group,
        public readonly bool $deleted,
        public readonly bool $ready,
    ) {
    }
}
