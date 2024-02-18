<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\SetErrorWhenBuildProject;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class SetErrorWhenBuildProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    public function handle(SetErrorWhenBuildProject $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $project->errorWhenBuild($command->error);
        $this->projectRepository->save($project);
    }
}
