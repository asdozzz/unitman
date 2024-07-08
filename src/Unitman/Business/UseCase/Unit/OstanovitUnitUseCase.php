<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class OstanovitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(OstanovitUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $jobId = $this->runnerService->nachatOstanovkuUnita($unit, $project);
        $unit->nachatOstanovkuUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
