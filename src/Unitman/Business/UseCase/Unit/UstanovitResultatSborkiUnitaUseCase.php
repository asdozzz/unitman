<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatSborkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private CanParseYaml $canParseYaml,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatSborkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatSborki = $this->runnerService->poluchitResultatSborki($unit);

        if ($resultatSborki->success) {
            $config = $this->canParseYaml->parse($resultatSborki->config ?? '');
            $unit->ustanovitUspehSborki($resultatSborki->steps, $config);
        } else {
            $unit->ustanovitOshibkuSborki($resultatSborki->steps);
        }
        $this->unitRepository->save($unit);
    }
}
