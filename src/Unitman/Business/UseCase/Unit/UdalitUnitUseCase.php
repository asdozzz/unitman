<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class UdalitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ProverkaSvobodyUnita $proverkaSvobody,
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $this->proverkaSvobody->proverit($unit);
        $unit->nachatUdalenieUnita($jobId);
        $this->runnerService->nachatUdalenieUnita($jobId, $unit);
        $this->unitRepository->save($unit);
    }

}
