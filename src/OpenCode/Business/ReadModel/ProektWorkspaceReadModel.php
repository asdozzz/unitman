<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\ProektWorkspace;

/**
 * Проект workspace в HTTP-ответах.
 *
 * Ни `storageUrl`, ни токен клонирования наружу не отдаются.
 */
final class ProektWorkspaceReadModel
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $projectCode,
        public readonly bool $canPush,
        public readonly int $positionIndex,
    ) {
    }

    public static function izModela(ProektWorkspace $project): self
    {
        return new self(
            $project->projectId,
            $project->projectName,
            $project->projectCode,
            $project->canPush,
            $project->positionIndex,
        );
    }

    /**
     * @return array{projectId: string, projectName: string, projectCode: string, canPush: bool, positionIndex: int}
     */
    public function toArray(): array
    {
        return [
            'projectId' => $this->projectId,
            'projectName' => $this->projectName,
            'projectCode' => $this->projectCode,
            'canPush' => $this->canPush,
            'positionIndex' => $this->positionIndex,
        ];
    }
}
