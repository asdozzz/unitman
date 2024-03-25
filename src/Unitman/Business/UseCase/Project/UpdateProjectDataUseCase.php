<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\Port\Project\CanFindProjectDouble;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class UpdateProjectDataUseCase
{
    public function __construct(
        private CanFindProjectDouble $canFindDouble,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(UpdateProjectData $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        if ($this->canFindDouble->isExistDoubleByName($command->id, $command->newProjectName)) {
            throw new \DomainException('project.double');
        }
        $project = $this->projectRepository->getById($command->id);
        $project->changeData($command);
        $this->projectRepository->save($project);
    }
}
