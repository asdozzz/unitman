<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class EnableProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    public function handle(EnableProject $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->id);
        $project->enable();
        $this->projectRepository->save($project);
    }
}
