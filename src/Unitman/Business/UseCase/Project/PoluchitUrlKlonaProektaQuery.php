<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;

/**
 * URL клонирования проекта с токеном. Используется только Runner.
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
        try {
            $project = $this->projectRepository->getById($projectId);
            $repo = $this->repoRepository->getById($project->getRepoId());
        } catch (\DomainException) {
            throw new \DomainException('opencode.workspace.project_not_found');
        }

        return $this->urlKlonaProekta->poluchitUrlKlonaProekta($repo, $project);
    }
}
