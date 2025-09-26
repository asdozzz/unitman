<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

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
        $this->runnerService->nachatSbrosPodgotovkiUnita($unitId, $unit);
        $this->unitRepository->save($unit);
    }
}
