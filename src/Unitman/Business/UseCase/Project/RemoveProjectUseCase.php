<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\RemoveProject;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class RemoveProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(RemoveProject $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $project->successfullyRemoving($command->info);
        $this->projectRepository->save($project);
    }
}
