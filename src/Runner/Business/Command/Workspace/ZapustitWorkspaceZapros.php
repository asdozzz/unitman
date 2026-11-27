<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Публичный запрос на запуск workspace-стенда.
 *
 * Приходит от OpenCode и **не содержит токен**: `StorageUrl` для каждого
 * проекта добавляет `RunnerApi` через `UmeetPoluchatUrlKlonaProekta`.
 * Внутрь workflow уходит уже обогащённая команда `ZapustitWorkspace`.
 */
final class ZapustitWorkspaceZapros
{
    /**
     * @param list<ProektWorkspaceZapros> $Projects
     * @param list<WorkspaceFileCommand>  $Files
     */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly string $WorkspaceName,
        public readonly string $WorkspaceSlug,
        public readonly string $Branch,
        public readonly bool $ReadOnly,
        public readonly int $MemoryLimit,
        public readonly array $Projects,
        // Явный раннер операции (multi-runner). `null` — выбрать автоматически.
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
        public readonly array $Files = [],
    ) {
    }
}
