<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос текущего прогресса выполнения задачи раннера.
 *
 * `prozesId` — идентификатор процесса юнита (read-модель процессов),
 * `zadachaId` — идентификатор задачи внутри процесса (он же jobId/workflowId).
 */
readonly class PoluchitProgressZadachiUnita implements JsonBodySerializableInterface
{
    public function __construct(
        public string $prozesId,
        public string $zadachaId,
    ) {
    }
}
