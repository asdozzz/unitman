<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ZapustitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService,
        private ProjectRepository $projectRepository
    )
    {
    }

    function handle(ZapustitUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatZapuskUnita($unit, $project);
        $unit->nachatZapuskUnita($jobId);
        $this->unitRepository->save($unit);
    }

    function handleTemporal(ZapustitUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $jobId = $this->runnerService->nachatZapuskUnita($unit, $project);
        $unit->nachatZapuskUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
