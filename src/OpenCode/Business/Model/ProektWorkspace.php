<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Снимок проекта workspace на момент создания: переименование проекта в
 * Unitman не меняет уже созданный workspace.
 */
final class ProektWorkspace
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $projectCode,
        public readonly bool $canPush,
        public readonly int $positionIndex,
    ) {
    }
}
