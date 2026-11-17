<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use Psr\Clock\ClockInterface;

final class ObnovitKodUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ClockInterface $clock,
        private ProverkaSvobodyUnita $proverkaSvobody,
    )
    {
    }

    function handleSystem(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $this->proverkaSvobody->proverit($unit);
        $unixtime = $this->clock->now()->getTimestamp();
        $unit->nachatObnovlenieUnita($jobId, $unixtime);
        $this->runnerService->nachatObnovlenieUnita($jobId, $unit);
        $this->unitRepository->save($unit);
    }
}
