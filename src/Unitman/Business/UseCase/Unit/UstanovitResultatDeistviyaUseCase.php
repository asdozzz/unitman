<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatDeistviya;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatDeistviyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatDeistviya $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatObnovleniya = $this->runnerService->poluchitResultatDeistviya($unit);

        if ($resultatObnovleniya->success) {
            $unit->ustanovitUspehDeistviya($resultatObnovleniya->steps);
        } else {
            $unit->ustanovitOshibkuDeistviya($resultatObnovleniya->steps);
        }

        $this->unitRepository->save($unit);
    }
}
