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
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(ProveritKonteinerUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);

        $konteinerZapushen = $this->runnerService->proveritKonteinerUnita($unit, $project);
        if (!$konteinerZapushen) {
            $unit->koneinerUnitaNeZapushen();
        }

        $this->unitRepository->save($unit);
    }
}
