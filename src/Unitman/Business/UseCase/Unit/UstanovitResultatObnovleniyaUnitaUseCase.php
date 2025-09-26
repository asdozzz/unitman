<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatObnovleniyaUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatObnovleniyaUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private CanParseYaml $canParseYaml,
        private RunnerService $runnerService
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $resultatObnovleniya = $this->runnerService->poluchitResultatObnovleniyaUnita($unitId);

        if ($resultatObnovleniya->success) {
            $config = $this->canParseYaml->parse($resultatObnovleniya->config ?? '');
            $unit->ustanovitUspehObnovleniya($jobId, $resultatObnovleniya->steps, $config);
        } else {
            $unit->ustanovitOshibkuObnovleniya($jobId, $resultatObnovleniya->steps);
        }

        $this->unitRepository->save($unit);
    }
}
