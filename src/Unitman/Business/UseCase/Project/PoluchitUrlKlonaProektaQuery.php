<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;

/**
 * Данные клонирования проекта. Используется только Runner.
 *
 * URL всегда без userinfo: токен выдаётся отдельным короткоживущим
 * грантом broker-эндпоинта.
 */
final class PoluchitUrlKlonaProektaQuery
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RepoRepository $repoRepository,
        private UmeetPoluchatUrlKlonaProekta $urlKlonaProekta,
    ) {
    }

    /**
     * @throws \DomainException opencode.workspace.project_not_found
     */
    public function handle(string $projectId): string
    {
        [$project, $repo] = $this->resolve($projectId);

        return $this->urlKlonaProekta->poluchitUrlKlonaProekta($repo, $project);
    }

    /**
     * @throws \DomainException opencode.workspace.project_not_found
     */
    public function handleRepoId(string $projectId): string
    {
        [$project] = $this->resolve($projectId);

        return $project->getRepoId();
    }

    /**
     * @return array{0: \App\Unitman\Business\Model\Project, 1: \App\Unitman\Business\Model\Repo}
     */
    private function resolve(string $projectId): array
    {
        try {
            $project = $this->projectRepository->getById($projectId);
            $repo = $this->repoRepository->getById($project->getRepoId());
        } catch (\DomainException) {
            throw new \DomainException('opencode.workspace.project_not_found');
        }

        return [$project, $repo];
    }
}
