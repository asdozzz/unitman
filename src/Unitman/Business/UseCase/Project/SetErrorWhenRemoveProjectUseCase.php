<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\SetErrorWhenRemoveProject;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class SetErrorWhenRemoveProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(SetErrorWhenRemoveProject $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $project->errorWhenRemoving($command->steps);
        $this->projectRepository->save($project);
    }
}
