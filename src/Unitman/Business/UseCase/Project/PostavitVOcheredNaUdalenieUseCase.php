<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;

final class PostavitVOcheredNaUdalenieUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService
    )
    {
    }

    public function handle(PostavitVOcheredNaUdalenie $command): void
    {
        $project = $this->projectRepository->getById($command->id);
        $jobId = $this->runnerService->removeProject($project);
        $project->postavitVOcheredNaUdanlenie($jobId);
        $this->projectRepository->save($project);
    }
}
