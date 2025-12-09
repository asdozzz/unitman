<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovki;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatSbrosaPodgotovkiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $resultatSbrosaPodgotovki = $this->runnerService->poluchitResultatSbrosaPodgotovkiUnita($jobId);
        if ($resultatSbrosaPodgotovki->success) {
            $unit->ustanovitUspehSbrosaPodgotovki($jobId, $resultatSbrosaPodgotovki->steps);
        } else {
            $unit->ustanovitOshibkuSbrosaPodgotovki($jobId, $resultatSbrosaPodgotovki->steps);
        }
        $this->unitRepository->save($unit);
    }
}
