<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\ProjectBuildState;

/**
 * Некорректное тело запроса записи факта сборки.
 *
 * Контракт: plans/runner-multi-runner.md §3.2 — код
 * `project_build_state.invalid_payload`.
 */
final class ProjectBuildStateInvalidPayloadException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('project_build_state.invalid_payload');
    }
}
