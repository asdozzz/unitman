<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class PodgotovitUnitKZapuskuUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $unit->nachatPodgotovkuUnita($jobId);
        $this->runnerService->nachatPodgotovkuUnita($jobId, $unit);
        $this->unitRepository->save($unit);
    }
}
