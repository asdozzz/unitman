<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Runner;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;

/**
 * Прогресс выполнения задачи раннера в терминах модуля Unitman.
 *
 * Формат полностью совпадает с payload push-канала Centrifugo
 * (`unit_progress#<jobId>`), поэтому фронтенд использует одинаковую логику
 * и для push-, и для pull-режима.
 */
final class RunnerJobProgress
{
    /**
     * @param list<RunnerJobStep> $steps
     */
    public function __construct(
        public readonly string $jobId,
        public readonly array $steps,
        public readonly bool $finished,
        public readonly bool $success,
        public readonly int $updatedAt,
    ) {
    }

    /**
     * `unixtimeStart` — необязательный ключ шага: эмитится только при наличии
     * времени начала, поэтому payload старых шагов не меняется.
     *
     * @return array{
     *     jobId: string,
     *     steps: list<array{command: string, response: string, success: bool, unixtime: int, unixtimeStart?: int}>,
     *     finished: bool,
     *     success: bool,
     *     updatedAt: int
     * }
     */
    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'steps' => array_map(
                static fn(RunnerJobStep $step): array => $step->toArray(),
                $this->steps,
            ),
            'finished' => $this->finished,
            'success' => $this->success,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
