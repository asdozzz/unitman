<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

final class ZapustitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(ZapustitUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $jobId = $this->runnerService->nachatZapuskUnita($unit);
        $unit->nachatZapuskUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
