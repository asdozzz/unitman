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

    function handle(UstanovitResultatSbrosaPodgotovki $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatSbrosaPodgotovki = $this->runnerService->poluchitResultatSbrosaPodgotovkiUnita($unit);
        if ($resultatSbrosaPodgotovki->success) {
            $unit->ustanovitUspehSbrosaPodgotovki($resultatSbrosaPodgotovki->steps);
        } else {
            $unit->ustanovitOshibkuSbrosaPodgotovki($resultatSbrosaPodgotovki->steps);
        }
        $this->unitRepository->save($unit);
    }
}
