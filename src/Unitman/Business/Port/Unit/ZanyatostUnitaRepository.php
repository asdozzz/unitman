<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\ZanyatostUnita;

/**
 * Реестр внешних захватов юнита.
 *
 * Захват обязан быть атомарным: между «проверил, что свободен» и «записал
 * себя владельцем» нет окна, иначе два параллельных хода OpenCode по одному
 * юниту оба увидят его свободным. Поэтому контракт возвращает `bool`:
 * `false` — юнит уже занят (в том числе тем же владельцем, но в прошлом ходе,
 * если его захват ещё не истёк).
 */
interface ZanyatostUnitaRepository
{
    /**
     * Пытается захватить юнит. `true` — захват наш, `false` — юнит занят.
     */
    public function zahvatit(
        string $unitId,
        string $vladelec,
        TipZanyatosti $tip,
        \DateTimeImmutable $istekaetV,
    ): bool;

    /**
     * Снимает захват, только если он принадлежит этому владельцу.
     * Идемпотентно: отсутствие строки — не ошибка.
     */
    public function osvobodit(string $unitId, string $vladelec): void;

    /**
     * Возвращает действующий (не истёкший) захват либо `null`.
     */
    public function poluchitAktivnuyu(string $unitId): ?ZanyatostUnita;

    /**
     * Удаляет истёкшие захваты, возвращает число удалённых строк.
     */
    public function udalitProsrochennie(\DateTimeImmutable $now): int;
}
