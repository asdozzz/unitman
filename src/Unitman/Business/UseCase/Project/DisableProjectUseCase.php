<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Port\ProjectRepository;

final class DisableProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(DisableProject $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->disable();
        $this->projectRepository->save($project);
    }
}
