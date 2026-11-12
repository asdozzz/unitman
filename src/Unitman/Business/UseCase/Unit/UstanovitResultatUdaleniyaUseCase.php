<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatUdaleniyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private MediatekaService $mediatekaService
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $resultatUdaleniya = $this->runnerService->poluchitResultatUdaleniyaUnita($jobId);

        if ($resultatUdaleniya->success) {
            $unit->ustanovitUspehUdaleniya($jobId, $resultatUdaleniya->steps);
            $this->unitRepository->save($unit);

            // Файлы юнита удаляем только после успешного удаления контейнера.
            $this->mediatekaService->udaliFayliUnita($unitId);

            return;
        }

        $unit->ustanovitOshibkuUdaleniya($jobId, $resultatUdaleniya->steps);
        $this->unitRepository->save($unit);
    }
}
