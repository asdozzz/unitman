<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatOstanovkiUnitaUseCase
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
        $resultatOstanovki = $this->runnerService->poluchitResultatOstanovkiUnita($jobId);
        if ($resultatOstanovki->success) {
            $unit->ustanovitUspehOstanovki($jobId, $resultatOstanovki->steps);
        } else {
            $unit->ustanovitOshibkuOstanovki($jobId, $resultatOstanovki->steps);
        }

        $this->unitRepository->save($unit);
    }
}
