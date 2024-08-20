<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UdalitUnitPosleZapuska;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UmeetUdalyatUnitPosleZapuska;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class UdalitUnitPosleZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UmeetUdalyatUnitPosleZapuska $umeetUdalyatUnitPosleZapuska,
        private UnitmanSecurityService $securityService
    )
    {
    }
    function handle(UdalitUnitPosleZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->umeetUdalyatUnitPosleZapuska->udalitUnitPosleZapuska($unit->getId());
        $unit->nachatUdalenieUnitaPosleZapuska($jobId);
        $this->unitRepository->save($unit);
    }
}
