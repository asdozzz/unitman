<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\Port\ProjectRepository;

final class RemoveUserFromProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(RemoveUserFromProject $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->removeUser($command);
        $this->projectRepository->save($project);
    }
}
