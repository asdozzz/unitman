<?php

declare(strict_types=1);

namespace App\Runner\Business\Model;

/**
 * Факт сборки проекта на конкретном раннере (multi-runner).
 *
 * Контракт: plans/runner-multi-runner.md, §3.
 */
final readonly class ProjectBuildState
{
    public function __construct(
        public string $runnerId,
        public string $projectId,
        public int $builtAt,
        public ?string $commit = null,
        public ?string $branch = null,
    ) {
    }
}
