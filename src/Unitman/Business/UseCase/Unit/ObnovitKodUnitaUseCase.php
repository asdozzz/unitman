<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class ObnovitKodUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(ObnovitKodUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $jobId = $this->runnerService->nachatObnovlenieUnita($unit);
        $unit->nachatObnovlenieUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
