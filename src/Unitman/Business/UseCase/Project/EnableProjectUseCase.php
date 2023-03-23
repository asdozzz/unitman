<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Port\ProjectRepository;

final class EnableProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(EnableProject $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $project->enable();
        $this->projectRepository->save($project);
    }
}
