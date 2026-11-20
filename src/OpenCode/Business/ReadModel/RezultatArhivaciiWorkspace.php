<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Результат попытки архивации workspace.
 *
 * `requiresConfirmation = true` означает, что в проектах есть незакоммиченные
 * изменения: повторный запрос должен прийти с `force = true`.
 */
final class RezultatArhivaciiWorkspace
{
    /**
     * @param list<array{projectId: string, name: string, files: list<string>}> $dirtyProjects
     */
    public function __construct(
        public readonly bool $requiresConfirmation,
        public readonly array $dirtyProjects,
        public readonly string $status,
    ) {
    }

    /**
     * @return array{
     *     requiresConfirmation: bool,
     *     dirtyProjects: list<array{projectId: string, name: string, files: list<string>}>,
     *     status: string
     * }
     */
    public function toArray(): array
    {
        return [
            'requiresConfirmation' => $this->requiresConfirmation,
            'dirtyProjects' => $this->dirtyProjects,
            'status' => $this->status,
        ];
    }
}
