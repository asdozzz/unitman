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
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $unit->nachatOstanovkuUnita($jobId);
        $this->runnerService->nachatOstanovkuUnita($jobId, $unit, $project);
        $this->unitRepository->save($unit);
    }
}
