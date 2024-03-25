<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatZapuska;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ProjectRepository $projectRepository
    )
    {
    }

    function handle(UstanovitResultatZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatZapuska = $this->runnerService->poluchitResultatZapuskaUnita($unit);
        if ($resultatZapuska->success) {
            $project = $this->projectRepository->getById($unit->getProjectId());
            $unit->ustanovitUspehZapuska($resultatZapuska->message, (string) $project->getProxyHost(), $project->getName());
        } else {
            $unit->ustanovitOshibkuZapuska($resultatZapuska->message);
        }
        $this->unitRepository->save($unit);
    }
}
