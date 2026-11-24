<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Запуск workspace-стенда на стороне Go-runner.
 *
 * Проекты приходят от вызывающей стороны уже с токенизированным `StorageUrl`:
 * Runner не обращается к Unitman за проектными данными, а только передаёт
 * подготовленную команду в workflow.
 */
final class ZapustitWorkspace
{
    /**
     * @param list<ProektWorkspaceCommand> $Projects
     */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly string $WorkspaceName,
        public readonly string $WorkspaceSlug,
        public readonly string $Branch,
        public readonly bool $ReadOnly,
        public readonly int $MemoryLimit,
        public readonly array $Projects,
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
    ) {
    }
}
