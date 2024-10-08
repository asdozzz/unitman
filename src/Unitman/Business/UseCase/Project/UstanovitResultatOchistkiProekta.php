<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;

final class UstanovitResultatOchistkiProekta
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
        $resultatSborki = $this->runnerService->poluchitResultatOchistkiProekta($project);
        if ($resultatSborki->success) {
            $project->ustanovitUspehOchistku($resultatSborki->steps);
        } else {
            $project->ustanovitOshibkuOchistku($resultatSborki->steps);
        }

        $this->projectRepository->save($project);
    }
}
