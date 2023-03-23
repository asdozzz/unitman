<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\CanFindProjectDouble;
use App\Unitman\Business\Port\ProjectRepository;

final class UpdateProjectDataUseCase
{
    public function __construct(
        private CanFindProjectDouble $canFindDouble,
        private ProjectRepository $projectRepository
    )
    {
    }

    function handle(UpdateProjectData $command): void
    {
        if ($this->canFindDouble->isExistDoubleByName($command->newProjectName)) {
            throw new \DomainException('project.double');
        }
        $project = $this->projectRepository->getById($command->projectId);
        $project->changeData($command);
        $this->projectRepository->save($project);
    }
}
