<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatIzmenenniyaVetkiUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatIzmeneniyaVetkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatIzmenenniyaVetkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultat = $this->runnerService->poluchitResultatIzmeneniyaVetki($unit);

        if ($resultat->success) {
            $unit->ustanovitUspehIzmeneniyaVetki($resultat->steps);
        } else {
            $unit->ustanovitOshibkuIzmeneniyaVetki($resultat->steps);
        }

        $this->unitRepository->save($unit);
    }
}
