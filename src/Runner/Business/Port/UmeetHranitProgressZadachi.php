<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RunnerProgress;

/**
 * Хранилище снапшотов прогресса задач раннера.
 *
 * Помимо самого снапшота хранит два служебных маркера:
 * - регистрацию токена (чтобы отбрасывать `saveSteps` по неизвестным токенам);
 * - время последней публикации (для троттлинга push-уведомлений).
 */
interface UmeetHranitProgressZadachi
{
    public function zapisat(RunnerProgress $progress): void;

    /**
     * Запись снапшота прогресса с уже сериализованными шагами.
     *
     * Нужна для `saveSteps`: контроллер декодирует и нормализует шаги один
     * раз, кодирует их в JSON один раз и передаёт готовый фрагмент в KV без
     * повторной сериализации массива. Формат payload идентичен
     * `RunnerProgress::toArray()` — `{"token","steps","finished","success","unixtime"}`.
     */
    public function zapisatSyrieProgress(
        string $token,
        string $stepsJson,
        bool $finished,
        bool $success,
        int $unixtime,
    ): void;

    /**
     * Запись «сырых» шагов под исходным ключом (`responseId`).
     *
     * Нужна для обратной совместимости: `RunnerApi::poluchitResultat*`
     * читает шаги по `ResponseId` из результата активити.
     */
    public function zapisatSyrieShagi(string $token, string $stepsContent): void;

    public function zaregistrirovat(string $token): void;

    public function estLiToken(string $token): bool;

    public function zapomnitVremyaPublikacii(string $token, int $unixtimeMs): void;

    public function poluchitVremyaPublikacii(string $token): ?int;
}
