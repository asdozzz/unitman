<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

final class ProveritIzmeneniyaWorkspace
{
    /**
     * @param list<array{ProjectId: string, ProjectName: string}> $Projects
     */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly array $Projects,
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
    ) {
    }
}
