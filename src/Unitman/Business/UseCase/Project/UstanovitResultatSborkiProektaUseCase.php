<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;

final class UstanovitResultatSborkiProektaUseCase
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
        $resultatSborki = $this->runnerService->poluchitResultatSborkiProekta($project);
        if ($resultatSborki->success) {
            $project->successfullyBuild($resultatSborki->steps);
        } else {
            $project->errorWhenBuild($resultatSborki->steps);
        }

        $this->projectRepository->save($project);
    }
}
