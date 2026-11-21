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
        public readonly string $mainBranch = '',
        public readonly string $branch = '',
    ) {
    }

    /**
     * Копия проекта с фактической веткой workspace.
     *
     * Модель неизменяемая, поэтому fallback на общую ветку workspace
     * материализуется отдельным экземпляром.
     */
    public function sVetkoy(string $branch): self
    {
        return new self(
            $this->projectId,
            $this->projectName,
            $this->projectCode,
            $this->canPush,
            $this->positionIndex,
            $this->mainBranch,
            $branch,
        );
    }
}
