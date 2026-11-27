<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Файл, прикреплённый к workspace.
 */
final class WorkspaceFileReadModel
{
    public function __construct(
        public readonly string $fileId,
        public readonly string $name,
        public readonly int $size,
        public readonly bool $applied,
        public readonly int $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fileId' => $this->fileId,
            'name' => $this->name,
            'size' => $this->size,
            'applied' => $this->applied,
            'createdAt' => $this->createdAt,
        ];
    }
}
