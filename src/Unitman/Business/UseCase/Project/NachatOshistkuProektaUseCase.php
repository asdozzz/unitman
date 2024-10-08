<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\NachatOchistkuProekta;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class NachatOshistkuProektaUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }
    function handle(NachatOchistkuProekta $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->id);
        $project->proverkaProektaDlyNachlaOchistki();

        $jobId = $this->runnerService->nachatOchistkuProekta($project);
        $project->nachatOchistku($jobId);

        $this->projectRepository->save($project);
    }
}
