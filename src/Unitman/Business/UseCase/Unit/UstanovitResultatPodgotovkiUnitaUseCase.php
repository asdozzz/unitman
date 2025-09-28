<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatPodgotovkiUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatPodgotovkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(string $unitId, string $jobId): bool
    {
        $unit = $this->unitRepository->getById($unitId);
        $resultatPodgotovki = $this->runnerService->poluchitResultatPodgotovki($jobId);
        if ($resultatPodgotovki->success) {
            $unit->ustanovitUspehPodgotovki($jobId, $resultatPodgotovki->steps);
        } else {
            $unit->ustanovitOshibkuPodgotovki($jobId, $resultatPodgotovki->steps);
        }
        $this->unitRepository->save($unit);

        return $resultatPodgotovki->success;
    }
}
