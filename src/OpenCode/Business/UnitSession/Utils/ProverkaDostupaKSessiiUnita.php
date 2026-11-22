<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\Utils;

use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\UnitSession\Model\UnitSession;

/**
 * Доступ к unit-сессии имеют только автор и прикреплённые редакторы юнита.
 *
 * Для удалённого юнита владелец сессии сохраняет доступ к истории, но новые
 * ходы всё равно запрещены гейтом готовности.
 */
final class ProverkaDostupaKSessiiUnita
{
    public function __construct(private UmeetUpravlyatUnitami $unitami)
    {
    }

    public function proverit(UnitSession $session, string $accountId): void
    {
        $sostoyanie = $this->unitami->poluchitSostoyanieUnita($session->getUnitId());

        if ($sostoyanie->exists && $sostoyanie->yavlyaetsyaRedaktorom($accountId)) {
            return;
        }

        if (!$sostoyanie->exists && $session->prinadlezhit($accountId)) {
            return;
        }

        throw new \DomainException('opencode.session.not_found');
    }
}
