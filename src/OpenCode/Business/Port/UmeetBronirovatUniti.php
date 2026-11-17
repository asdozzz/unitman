<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

/**
 * Захват юнитов под ход OpenCode.
 *
 * Пока ход выполняется, юнит занят: активити Unitman над ним не запускаются,
 * а события вебхука откладываются до освобождения. Захват идёт по всем юнитам
 * сессии, иначе второй юнит остаётся доступным, пока OpenCode пишет в него файлы.
 *
 * `vladelec` — непрозрачный токен владельца (`opencode:<turnId>`): освободить
 * захват может только тот, кто его поставил. Контракт «всё или ничего»: при
 * отказе по одному юниту уже захваченные освобождаются.
 */
interface UmeetBronirovatUniti
{
    /**
     * @param string[] $unitIds
     * @throws \DomainException `unit.zanyat`, если хотя бы один юнит занят
     */
    public function zahvatit(array $unitIds, string $vladelec): void;

    /**
     * Идемпотентно: повторное освобождение не ошибка.
     *
     * @param string[] $unitIds
     */
    public function osvobodit(array $unitIds, string $vladelec): void;
}
