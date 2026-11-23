<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\WorkspaceUnit;

/**
 * Юнит, созданный из workspace, в HTTP-ответах.
 */
final class WorkspaceUnitReadModel
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
    ) {
    }

    public static function izModela(WorkspaceUnit $unit): self
    {
        return new self(
            $unit->unitId,
            $unit->projectId,
            $unit->unitName,
            $unit->branch,
        );
    }

    /**
     * @return array{unitId: string, projectId: string, unitName: string, branch: string}
     */
    public function toArray(): array
    {
        return [
            'unitId' => $this->unitId,
            'projectId' => $this->projectId,
            'unitName' => $this->unitName,
            'branch' => $this->branch,
        ];
    }
}
