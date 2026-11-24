<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\ProjectBuildState;

/**
 * Некорректный идентификатор проекта в пути build-state эндпоинта.
 *
 * Контракт: plans/runner-multi-runner.md §3.1 — код
 * `project_build_state.invalid_project_id`.
 */
final class ProjectBuildStateInvalidProjectIdException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('project_build_state.invalid_project_id');
    }
}
