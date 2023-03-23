<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Account\Business\Port\UuidGenerator;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanFindUnitDouble;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\SecurityService;
use App\Unitman\Business\Port\UnitRepository;

final class SobratUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
    )
    {
    }

    function handle(SobratUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $jobId = $this->runnerService->nachatSborkuUnita($unit);
        $unit->nachatSborkuUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
