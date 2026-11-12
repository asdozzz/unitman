<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Utils\EventSauce\ProjectionsManager;

final class SqlProjectEventsRepository implements ProjectRepository
{
    public function __construct(
        private ProjectionsManager $projectionsManager
    )
    {}
    public function getById(string $id): Project
    {
        $repo = $this->projectionsManager->retrieve(Project::class, Project\ProjectId::fromString($id));
        /** @var Project $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('project.not_found');
        }
        return $repo;
    }

    public function save(Project $project): void
    {
        $this->projectionsManager->persistAndPullProjections($project);
    }
}
