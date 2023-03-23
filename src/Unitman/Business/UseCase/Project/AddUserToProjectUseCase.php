<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Port\ProjectRepository;

final class AddUserToProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(AddUserToProject $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->addUser($command);
        $this->projectRepository->save($project);
    }
}
