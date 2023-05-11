<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ForceRemoveProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    public function handle(ForceRemoveProject $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->projectId);
        $project->removeManually();
        $this->projectRepository->save($project);
    }
}
