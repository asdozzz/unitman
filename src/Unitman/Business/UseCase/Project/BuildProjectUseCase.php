<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\BuildProject;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class BuildProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(BuildProject $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $project->successfullyBuild($command->steps);
        $this->projectRepository->save($project);
    }
}
