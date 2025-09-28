<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ProveritKonteinerUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ProveritKonteinerUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $konteinerZapushen = $this->runnerService->proveritKonteinerUnita($unit, $project);
        if (!$konteinerZapushen) {
            $unit->koneinerUnitaNeZapushen();
        }

        $this->unitRepository->save($unit);
    }
}
