<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatUdaleniyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatUdaleniya $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatUdaleniya = $this->runnerService->poluchitResultatUdaleniyaUnita($unit);
        if ($resultatUdaleniya->success) {
            $unit->ustanovitUspehUdaleniya($resultatUdaleniya->steps);
        } else {
            $unit->ustanovitOshibkuUdaleniya($resultatUdaleniya->steps);
        }
        $this->unitRepository->save($unit);
    }
}
