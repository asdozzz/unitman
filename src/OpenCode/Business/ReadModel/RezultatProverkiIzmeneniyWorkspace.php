<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Результат проверки незакоммиченных изменений в проектах workspace.
 */
final class RezultatProverkiIzmeneniyWorkspace
{
    /**
     * @param list<array{projectId: string, name: string, dirty: bool, files: list<string>}> $projects
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $projects,
    ) {
    }
}
