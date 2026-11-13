<?php

namespace App\Runner\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Шаги выполнения задачи раннера.
 *
 * `responseId` — токен прогресса (для наших активити он равен workflowId/jobId,
 * см. `plans/runner-progress-streaming.md`).
 *
 * `stepsContent` — ПОЛНЫЙ снапшот шагов на текущий момент, а не дельта.
 * Такой контракт позволяет клиенту просто заменять список и не терять шаги
 * при потере отдельного push-сообщения.
 *
 * `finished`/`success` — необязательные признаки завершения: если раннер их
 * присылает, UI закрывает прогресс не дожидаясь события обновления юнита.
 */
final class SaveStepsCommand implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $responseId,
        public readonly string $stepsContent,
        public readonly bool $finished = false,
        public readonly bool $success = false,
    )
    {
    }

}
