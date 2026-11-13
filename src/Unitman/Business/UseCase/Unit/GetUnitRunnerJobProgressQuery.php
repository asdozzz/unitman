<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitProgressZadachiUnita;
use App\Unitman\Business\Model\Runner\RunnerJobProgress;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UmeetPoluchatProzesiUnitaPoId;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita\ShagZadachiUnita;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita\ZadachaProzesaUnita;

/**
 * Выдаёт текущий прогресс выполнения задачи раннера (pull-режим).
 *
 * Сначала проверяет, что задача действительно принадлежит указанному процессу
 * юнита — это исключает доступ к прогрессу чужого jobId по угаданному идентификатору.
 *
 * Источник прогресса выбирается по состоянию задачи в БД:
 *  - если задача уже завершена (SUCCESS/ERROR/CANCLED) и в read-модели есть шаги,
 *    прогресс собирается из БД — данные переживают истечение TTL снимка в KV;
 *  - иначе берётся снимок из Runner через ACL-порт (KV), а при его отсутствии
 *    возвращается пустой прогресс.
 *
 * Пустой прогресс нужен, когда раннер ещё не успел прислать ни одного
 * `saveSteps`: фронтенд отрисует «выполняется» и дождётся первого push-события.
 */
final class GetUnitRunnerJobProgressQuery
{
    public function __construct(
        private UmeetPoluchatProzesiUnitaPoId $canGetUnitRunnerJobs,
        private RunnerService $runnerService,
    ) {
    }

    public function handle(PoluchitProgressZadachiUnita $command): RunnerJobProgress
    {
        // Проверка принадлежности задачи процессу: при отсутствии —
        // DomainException('unit.runner_job_not_found'), которая транслируется
        // в человекочитаемую ошибку ExceptionTranslator-ом.
        $zadacha = $this->canGetUnitRunnerJobs->poluchitZadachuPoId($command->prozesId, $command->zadachaId);

        if ($zadacha->isFinished() && $zadacha->steps !== []) {
            return $this->progressIzBd($zadacha);
        }

        return $this->runnerService->poluchitProgressZadachi($command->zadachaId)
            ?? new RunnerJobProgress($command->zadachaId, [], false, false, 0);
    }

    /**
     * Собирает прогресс из read-модели БД. Формат совпадает с push-каналом,
     * чтобы фронтенд обрабатывал оба источника одной логикой.
     */
    private function progressIzBd(ZadachaProzesaUnita $zadacha): RunnerJobProgress
    {
        return new RunnerJobProgress(
            $zadacha->id,
            array_map(
                static fn (ShagZadachiUnita $step): RunnerJobStep => new RunnerJobStep(
                    $step->command,
                    $step->response,
                    $step->success,
                    $step->unixtime,
                    $step->unixtimeStart,
                ),
                $zadacha->steps,
            ),
            true,
            $zadacha->isSuccess(),
            $zadacha->getLastUnixtime(),
        );
    }
}
