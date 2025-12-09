<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatDeistviyaUseCase
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
        $resultatObnovleniya = $this->runnerService->poluchitResultatDeistviya($jobId);

        if ($resultatObnovleniya->success) {
            $unit->ustanovitUspehDeistviya($jobId, $resultatObnovleniya->steps);
        } else {
            $unit->ustanovitOshibkuDeistviya($jobId, $resultatObnovleniya->steps);
        }

        $this->unitRepository->save($unit);
    }
}
