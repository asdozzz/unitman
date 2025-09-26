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
        private RunnerService $runnerService
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $resultatZapuska = $this->runnerService->poluchitResultatZapuskaUnita($unitId);
        if ($resultatZapuska->success) {
            $unit->ustanovitUspehZapuska($jobId,$resultatZapuska->steps);
        } else {
            $unit->ustanovitOshibkuZapuska($jobId,$resultatZapuska->steps);
        }
        $this->unitRepository->save($unit);
    }
}
