<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;

/**
 * Durable-хранилище отложенных действий из вебхука.
 *
 * Ключ уникальности — пара (`unit_id`, `tip_sobitiya`): повторный вебхук по
 * той же ветке не должен плодить строки. Приоритет `VETKA_UDALENA` над
 * `KOD_OBNOVLEN` обеспечивает вызывающая сторона, удаляя намерения обновления
 * при постановке удаления.
 */
interface OtlozhennieDeistviyaRepository
{
    /**
     * Создаёт или обновляет намерение (upsert по `unit_id` + `tip_sobitiya`).
     */
    public function otlozhit(
        string $unitId,
        string $projectId,
        TipSobitiya $tipSobitiya,
        array $payload,
        \DateTimeImmutable $now,
    ): void;

    /**
     * Намерения юнита, готовые к применению прямо сейчас.
     *
     * @return OtlozhennoeDeistvie[]
     */
    public function poluchitGotovieDlyaUnita(string $unitId, \DateTimeImmutable $now): array;

    /**
     * Намерения, у которых наступило `next_attempt_at`, для фонового обхода.
     * Выборка с `FOR UPDATE SKIP LOCKED` — несколько воркеров не мешают друг другу.
     *
     * @return OtlozhennoeDeistvie[]
     */
    public function poluchitGotovie(\DateTimeImmutable $now, int $limit = 50): array;

    /**
     * Удаляет намерение, признав его применённым.
     */
    public function udovletvorit(string $unitId, TipSobitiya $tipSobitiya): void;

    /**
     * Фиксирует неудачную попытку: увеличивает `attempts`, сдвигает
     * `next_attempt_at`, пишет текст последней ошибки.
     */
    public function otmetitPopytku(
        string $unitId,
        TipSobitiya $tipSobitiya,
        \DateTimeImmutable $nextAttemptAt,
        ?string $lastError,
    ): void;

    public function udalitPoUnituITipu(string $unitId, TipSobitiya $tipSobitiya): void;

    /**
     * Удаляет намерения старше указанного момента, возвращает число строк.
     */
    public function udalitUstarevshie(\DateTimeImmutable $before): int;
}
