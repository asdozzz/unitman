<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Project;

/**
 * Проект, доступный для workspace-сессии OpenCode.
 *
 * `storageUrl` содержит токен и отдаётся только Runner; через HTTP OpenCode
 * это поле не публикуется.
 */
final class ProektDlyaWorkspaceReadModel
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $projectCode,
        public readonly string $mainBranch,
        public readonly bool $canPush,
        public readonly string $storageUrl,
        public readonly int $idePort,
    ) {
    }
}
