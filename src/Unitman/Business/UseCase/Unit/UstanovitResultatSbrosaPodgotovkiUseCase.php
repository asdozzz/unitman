<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovki;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

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
        $unit = $this->unitRepository->getById($command->unitId);
        $resultatSbrosaPodgotovki = $this->runnerService->poluchitResultatSbrosaPodgotovkiUnita($unit);
        if ($resultatSbrosaPodgotovki->success) {
            $unit->ustanovitUspehSbrosaPodgotovki($resultatSbrosaPodgotovki->message);
        } else {
            $unit->ustanovitOshibkuSbrosaPodgotovki($resultatSbrosaPodgotovki->message);
        }
        $this->unitRepository->save($unit);
    }
}
