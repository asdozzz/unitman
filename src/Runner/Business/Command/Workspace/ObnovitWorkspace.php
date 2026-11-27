<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Внутренняя команда обновления workspace для Temporal.
 *
 * Новые проекты уже содержат чистый `StorageUrl` и `RepoId`; токен
 * подставляется runner-ом just-in-time.
 */
final class ObnovitWorkspace
{
    /**
     * @param list<ProektWorkspaceCommand> $NewProjects
     * @param list<ProektWorkspaceZapros>  $AllProjects
     * @param list<WorkspaceFileCommand>   $Files
     * @param list<string>                 $FilesToDelete имена файлов
     */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly string $WorkspaceSlug,
        public readonly string $Branch,
        public readonly bool $ReadOnly,
        public readonly array $NewProjects,
        public readonly array $AllProjects,
        public readonly array $Files = [],
        public readonly array $FilesToDelete = [],
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
    ) {
    }
}
