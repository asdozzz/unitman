<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Публичный запрос на обновление workspace: новые проекты и файлы.
 *
 * Не содержит `StorageUrl`: URL для новых проектов добавляет RunnerApi.
 */
final class ObnovitWorkspaceZapros
{
    /**
     * @param list<ProektWorkspaceZapros> $NewProjects
     * @param list<WorkspaceFileCommand>  $Files
     * @param list<string>                $FilesToDelete имена файлов
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
