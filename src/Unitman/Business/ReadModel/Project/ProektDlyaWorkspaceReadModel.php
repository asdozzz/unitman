<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Project;

/**
 * Проект, доступный для workspace-сессии OpenCode.
 *
 * `storageUrl` — чистый remote URL без userinfo. Токен раннер получает
 * отдельным короткоживущим грантом по `repoId`; через HTTP OpenCode
 * эти поля не публикуются.
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
        public readonly string $repoId = '',
    ) {
    }
}
