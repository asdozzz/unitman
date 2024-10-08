<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;

final class UstanovitResultatUdaleniyaProektaUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(string $projectId): void
    {
        $project = $this->projectRepository->getById($projectId);
        $resultatSborki = $this->runnerService->poluchitResultatUdaleniyaProekta($project);
        if ($resultatSborki->success) {
            $project->successfullyRemoving($resultatSborki->steps);
        } else {
            $project->errorWhenRemoving($resultatSborki->steps);
        }

        $this->projectRepository->save($project);
    }
}
