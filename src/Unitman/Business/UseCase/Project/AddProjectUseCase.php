<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\CanFindProjectDouble;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class AddProjectUseCase
{
    public function __construct(
        private CanFindProjectDouble $canFindDouble,
        private CanGeneateGuid $canGeneateGuid,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(AddProject $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        if ($this->canFindDouble->isExistDouble($command->projectCode, $command->projectName)) {
            throw new \DomainException('project.double');
        }

        $projectId = $this->canGeneateGuid->makeGuid();

        $project = Project::addProject($projectId, $command);
        $this->projectRepository->save($project);
    }
}
