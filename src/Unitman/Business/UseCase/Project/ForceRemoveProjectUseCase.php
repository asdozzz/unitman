<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Port\ProjectRepository;

final class ForceRemoveProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(ForceRemoveProject $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->removeManually();
        $this->projectRepository->save($project);
    }
}
