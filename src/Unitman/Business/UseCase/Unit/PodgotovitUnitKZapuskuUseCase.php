<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

final class PodgotovitUnitKZapuskuUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(PodgotovitUnitKZapusku $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $jobId = $this->runnerService->nachatPodgotovkuUnita($unit);
        $unit->nachatPodgotovkuUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
