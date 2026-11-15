<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;

/**
 * Гейт старта хода: сессия принимает сообщения только тогда, когда готовы
 * ВСЕ её юниты (запущены либо завершились с ошибкой).
 *
 * Юниты не знают о сессиях, поэтому проверка выполняется здесь — на входе в ход,
 * по запросу к Unitman. Удалённые юниты исключаются из хода (и из multi-root
 * workspace), но остаются в списке сессии; когда удалены все юниты, сессия
 * переводится в архив и ход отклоняется. Если хоть один живой юнит не готов,
 * ход тоже отклоняется, но сессия остаётся активной, чтобы UI мог показать
 * «юнит собирается».
 */
final class PropuskHodaOpenCode
{
    public function __construct(
        private UmeetUpravlyatUnitami $unitami,
        private SessiyaOpenCodeRepository $sessionRepository,
    ) {
    }

    /**
     * Проверяет готовность всех юнитов сессии и возвращает те из них,
     * в директориях которых действительно должен выполняться ход.
     *
     * @return list<PrivyazkaKUnitu> живые юниты в порядке привязки (первый — cwd)
     *
     * @throws \DomainException если сессия в архиве, все юниты удалены или хотя бы один юнит не готов
     */
    public function proverit(SessiyaOpenCode $session): array
    {
        $session->trebovatAktivnuyu();

        if (!$session->privezanaKUnitu()) {
            return [];
        }

        $zhivye = [];
        $estNeudalennyi = false;

        foreach ($session->getUnits() as $privyazka) {
            $sostoyanie = $this->unitami->poluchitSostoyanieUnita($privyazka->unitId);

            if ($sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED) {
                continue;
            }

            $estNeudalennyi = true;

            if ($sostoyanie->group !== SostoyanieUnita::GROUP_READY) {
                throw new \DomainException('opencode.session.unit_not_ready');
            }

            $zhivye[] = $privyazka;
        }

        if (!$estNeudalennyi) {
            $session->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, time());
            $this->sessionRepository->update($session);

            throw new \DomainException('opencode.session.archived');
        }

        return $zhivye;
    }
}
