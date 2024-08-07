<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ObnovitKodUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(ObnovitKodUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatObnovlenieUnita($unit);
        $unit->nachatObnovlenieUnita($jobId);
        $this->unitRepository->save($unit);
    }

    function handleTemporal(ObnovitKodUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($unit->getAuthorId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatObnovlenieUnita($unit);
        $unit->nachatObnovlenieUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
