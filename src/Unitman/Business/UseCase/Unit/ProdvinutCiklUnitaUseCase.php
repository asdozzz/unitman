<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Unit\UnitRepository;

final class ProdvinutCiklUnitaUseCase
{
    private const KOD_SOBRAN = 'USPESHNO_SOBRAN';
    private const KOD_PODGOTOVLEN_K_ZAPUSKU = 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU';

    public function __construct(
        private UnitRepository $unitRepository,
        private DobavitProzesPodgotovkiUseCase $dobavitProzesPodgotovkiUseCase,
        private DobavitProzesZapuskaUseCase $dobavitProzesZapuskaUseCase,
    )
    {
    }

    /**
     * Идемпотентный шаг конвейера юнита от имени явно переданного пользователя.
     * Нужен интеграциям (сессиям OpenCode): конвейер не продвигается сам,
     * его обычно двигает пользователь через UI. Если этап уже в работе или
     * в очереди — ничего не делаем; гонки гасятся доменными проверками
     * внутри `dobavitProzes*`.
     */
    function handle(string $unitId, string $accountId): void
    {
        $unitId = trim($unitId);
        $accountId = trim($accountId);

        if ($unitId === '' || $accountId === '') {
            return;
        }

        try {
            $unit = $this->unitRepository->getById($unitId);
        } catch (\DomainException) {
            return;
        }

        $code = $unit->poluchitKodSostoyaniya();

        if ($code !== self::KOD_SOBRAN && $code !== self::KOD_PODGOTOVLEN_K_ZAPUSKU) {
            return;
        }

        if ($unit->esliEstNeobrabotanieProzesi()) {
            return;
        }

        try {
            /*
             * `proveritSvobodu: false` — шаг конвейера двигает сам OpenCode,
             * который как раз и держит захват юнита. Проверка свободы здесь
             * заблокировала бы ровно того, ради кого захват и ставился.
             */
            if ($code === self::KOD_SOBRAN) {
                $this->dobavitProzesPodgotovkiUseCase->handleDlyaPolzovatelya($unitId, $accountId, false);

                return;
            }

            $this->dobavitProzesZapuskaUseCase->handleDlyaPolzovatelya($unitId, $accountId, false);
        } catch (\DomainException) {
            // Состояние успело измениться между чтением и записью: считаем,
            // что цикл уже продвинут кем-то другим.
        }
    }
}
