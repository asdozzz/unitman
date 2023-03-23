<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

final class SbrositPodgotovkuUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(ObnovitKodUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $jobId = $this->runnerService->nachatSbrosPodgotovkiUnita($unit);
        $unit->nachatSbrosPodgotovkiUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
