<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Состояние сессии для интерфейса: почему ходы заблокированы.
 *
 * `units` — состояние всех привязанных юнитов в порядке привязки (первый —
 * рабочий каталог хода). `unitId`/`unitProjectId` продублированы ради обратной
 * совместимости и указывают на первый юнит.
 *
 * `canAcceptMessages` считается по тому же правилу, что и гейт старта хода
 * (`PropuskHodaOpenCode`): сессия не в архиве, у неё есть хотя бы один живой
 * юнит и все живые юниты готовы. Свободная сессия (без юнитов) принимает
 * сообщения, пока не архивирована.
 *
 * `unitStateCode` — код первого блокирующего юнита; если блокировки нет,
 * подставляется код первого юнита (`null` для свободной сессии).
 */
final class SostoyanieSessiiReadModel
{
    /**
     * @param list<SostoyanieUnitaSessiiReadModel> $units
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $units,
        public readonly ?string $unitId,
        public readonly ?string $unitProjectId,
        public readonly bool $archived,
        public readonly ?string $archiveReason,
        public readonly ?string $unitStateCode,
        public readonly bool $canAcceptMessages,
    ) {
    }
}
