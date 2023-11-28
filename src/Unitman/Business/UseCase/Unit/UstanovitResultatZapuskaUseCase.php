<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatZapuska;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitResultatZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatZapuska = $this->runnerService->poluchitResultatZapuskaUnita($unit);
        if ($resultatZapuska->success) {
            $unit->ustanovitUspehZapuska($resultatZapuska->message);
        } else {
            $unit->ustanovitOshibkuZapuska($resultatZapuska->message);
        }
        $this->unitRepository->save($unit);
    }
}
