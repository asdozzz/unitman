<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Юнит, созданный из workspace, и его проект.
 *
 * Хранится снимком в `opencode_workspace_unit`, чтобы карточка workspace
 * показывала созданные юниты без дополнительных запросов в Unitman.
 */
final class WorkspaceUnit
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['unitId'] ?? ''),
            (string) ($data['projectId'] ?? ''),
            (string) ($data['unitName'] ?? ''),
            (string) ($data['branch'] ?? ''),
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
