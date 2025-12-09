<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class AddUserToProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    public function handle(AddUserToProject $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $user = $this->securityService->getUserById($command->userId);

        $project = $this->projectRepository->getById($command->id);
        $project->addUser($user);
        $this->projectRepository->save($project);
    }
}
