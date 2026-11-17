<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\ReadModel\Unit\ZanyatostUnitaReadModel;

/**
 * Ответ на вопрос «занят ли юнит и почему».
 *
 * Объединяет два источника, потому что вызывающему модулю нужен один ответ:
 *
 * - реестр `unit_zanyatost` — внешние владельцы (ход OpenCode);
 * - агрегат — незавершённые собственные процессы юнита.
 *
 * Второй источник не дублируется в таблице: он уже есть в event store, и любая
 * копия неизбежно разошлась бы с ним.
 */
final class PoluchitZanyatostUnitaQuery
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ZanyatostUnitaRepository $zanyatostRepository,
    ) {
    }

    /**
     * Отсутствующий юнит считается свободным: вызывающий сам решает, что делать
     * с «юнита нет» (в OpenCode это отдельное состояние `exists = false`).
     */
    public function handle(string $unitId): ZanyatostUnitaReadModel
    {
        $unitId = trim($unitId);

        if ($unitId === '') {
            return ZanyatostUnitaReadModel::svoboden();
        }

        try {
            $unit = $this->unitRepository->getById($unitId);
        } catch (\DomainException) {
            return ZanyatostUnitaReadModel::svoboden();
        }

        return $this->dlyaUnita($unit);
    }

    /**
     * Вариант для вызывающего, у которого агрегат уже загружен: повторное
     * чтение из event store на опрашиваемом эндпоинте бессмысленно дорого.
     */
    public function dlyaUnita(Unit $unit): ZanyatostUnitaReadModel
    {
        $unitId = $unit->getId();
        $zanyatost = $this->zanyatostRepository->poluchitAktivnuyu($unitId);
        $zanyatProzesami = $unit->esliEstNeobrabotanieProzesi() || $unit->isWaitResultFromRunner();

        if ($zanyatost === null) {
            return new ZanyatostUnitaReadModel(false, $zanyatProzesami);
        }

        return new ZanyatostUnitaReadModel(
            true,
            $zanyatProzesami,
            $zanyatost->tip,
            $zanyatost->istekaetV,
        );
    }
}
