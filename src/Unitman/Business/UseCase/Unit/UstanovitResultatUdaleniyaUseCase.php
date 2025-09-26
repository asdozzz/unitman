<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatUdaleniyaUseCase
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
        $resultatUdaleniya = $this->runnerService->poluchitResultatUdaleniyaUnita($unitId);
        if ($resultatUdaleniya->success) {
            $unit->ustanovitUspehUdaleniya($jobId, $resultatUdaleniya->steps);
        } else {
            $unit->ustanovitOshibkuUdaleniya($jobId, $resultatUdaleniya->steps);
        }
        $this->unitRepository->save($unit);
    }
}
