<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

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
        $unit = $this->unitRepository->getById($command->unitId);
        $resultatOstanovki = $this->runnerService->poluchitResultatOstanovkiUnita($unit);
        if ($resultatOstanovki->success) {
            $unit->ustanovitUspehOstanovki($resultatOstanovki->message);
        } else {
            $unit->ustanovitOshibkuOstanovki($resultatOstanovki->message);
        }

        $this->unitRepository->save($unit);
    }
}
