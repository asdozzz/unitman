<?php

declare(strict_types=1);

namespace App\Unitman\Business\Utils;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;

/**
 * Единая точка ответа на вопрос «можно ли сейчас трогать юнит».
 *
 * Зачем отдельная утилита, а не проверки по месту: вопрос задаётся из двух
 * принципиально разных мест — из мутирующих UseCase (жёсткий отказ) и из
 * обработчика вебхука (мягкое решение «отложить»). Если бы правило жило в
 * двух местах, они неизбежно разошлись бы, и вебхук начал бы откладывать
 * то, что UseCase уже разрешает, или наоборот.
 *
 * Проверяется только внешний захват. Собственные незавершённые процессы юнита
 * (`unit.wait_another_proccess`, `unit.udalen`) остаются заботой агрегата:
 * он видит их в event store и отказывает сам.
 */
final class ProverkaSvobodyUnita
{
    public function __construct(private ZanyatostUnitaRepository $zanyatostRepository)
    {
    }

    /**
     * Бросает доменное исключение, если юнит занят ходом OpenCode.
     *
     * @throws \DomainException
     */
    public function proverit(Unit $unit): void
    {
        if (!$this->svoboden($unit)) {
            throw new \DomainException('unit.zanyat_hodom_opencode');
        }
    }

    /**
     * Тихо отвечает, свободен ли юнит.
     *
     * Занятость процессами здесь намеренно не учитывается: вызывающий
     * обработчик вебхука должен откладывать событие только при **временной**
     * занятости, а незавершённые процессы уже покрыты проверками агрегата,
     * которые бросают `unit.wait_another_proccess` и оставляют поведение
     * прежним.
     */
    public function svoboden(Unit $unit): bool
    {
        $zanyatost = $this->zanyatostRepository->poluchitAktivnuyu($unit->getId());

        if ($zanyatost === null) {
            return true;
        }

        return $zanyatost->tip !== TipZanyatosti::OPENCODE_HOD;
    }
}
