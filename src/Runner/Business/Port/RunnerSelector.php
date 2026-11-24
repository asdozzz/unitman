<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RunnerState;

/**
 * Выбор живого раннера для ресурсоёмкой операции (multi-runner).
 *
 * Контракт: plans/runner-multi-runner.md, §6.
 */
interface RunnerSelector
{
    /**
     * Выбирает самый свободный раннер.
     *
     * Кандидаты обязаны быть активными, со свежим health и без блокировки
     * ресурсов. Tie-break — меньше привязанных юнитов, затем `runnerId`.
     *
     * @param RunnerState[]     $runners
     * @param array<string,int> $boundUnitsByRunner runnerId => число привязанных юнитов
     *
     * @throws \DomainException `runner.not_found`, если живых раннеров нет
     */
    public function select(array $runners, array $boundUnitsByRunner = []): RunnerState;
}
