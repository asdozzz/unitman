<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatOstanovkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatOstanovkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatOstanovki = $this->runnerService->poluchitResultatOstanovkiUnita($unit);
        if ($resultatOstanovki->success) {
            $unit->ustanovitUspehOstanovki($resultatOstanovki->steps);
        } else {
            $unit->ustanovitOshibkuOstanovki($resultatOstanovki->steps);
        }

        $this->unitRepository->save($unit);
    }
}
