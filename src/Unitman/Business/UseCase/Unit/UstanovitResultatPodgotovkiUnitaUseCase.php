<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatPodgotovkiUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitResultatPodgotovkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatPodgotovkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatPodgotovki = $this->runnerService->poluchitResultatPodgotovki($unit);
        if ($resultatPodgotovki->success) {
            $unit->ustanovitUspehPodgotovki($resultatPodgotovki->message);
        } else {
            $unit->ustanovitOshibkuPodgotovki($resultatPodgotovki->message);
        }
        $this->unitRepository->save($unit);
    }
}
