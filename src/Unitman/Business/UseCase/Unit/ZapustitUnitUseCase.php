<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class ZapustitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ProjectRepository $projectRepository
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $unit->nachatZapuskUnita($jobId);
        $this->runnerService->nachatZapuskUnita($jobId, $unit, $project);
        $this->unitRepository->save($unit);
    }
}
