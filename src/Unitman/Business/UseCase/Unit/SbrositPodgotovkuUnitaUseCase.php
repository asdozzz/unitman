<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class SbrositPodgotovkuUnitaUseCase
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
        $unit->nachatSbrosPodgotovkiUnita($jobId);
        $this->runnerService->nachatSbrosPodgotovkiUnita($jobId, $unit);
        $this->unitRepository->save($unit);
    }
}
