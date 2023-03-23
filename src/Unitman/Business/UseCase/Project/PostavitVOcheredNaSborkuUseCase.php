<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;

final class PostavitVOcheredNaSborkuUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService
    )
    {
    }

    public function handle(PostavitVOcheredNaSborku $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $jobId = $this->runnerService->buildProject($project);
        $project->postavitVOcheredNaSborku($jobId);
        $this->projectRepository->save($project);
    }
}
