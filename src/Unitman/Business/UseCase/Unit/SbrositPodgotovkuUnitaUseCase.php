<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class SbrositPodgotovkuUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(SbrositPodgotovkuUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatSbrosPodgotovkiUnita($unit);
        $unit->nachatSbrosPodgotovkiUnita($jobId);
        $this->unitRepository->save($unit);
    }

    function handleTemporal(SbrositPodgotovkuUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->validaziyaPeredSbrosomPodgotovkoi();
        $jobId = $this->runnerService->nachatSbrosPodgotovkiUnita($unit);
        $unit->nachatSbrosPodgotovkiUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
