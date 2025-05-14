<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PodgotovitUnitKZapuskuUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(PodgotovitUnitKZapusku $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $unit->validaziyaPeredPodgotovkoi();
        $jobId = $this->runnerService->nachatPodgotovkuUnita($unit);
        $unit->nachatPodgotovkuUnita($jobId);
        $this->unitRepository->save($unit);
    }

    function handleTemporal(PodgotovitUnitKZapusku $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->validaziyaPeredPodgotovkoi();
        $jobId = $this->runnerService->nachatPodgotovkuUnita($unit);
        $unit->nachatPodgotovkuUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
