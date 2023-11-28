<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitResultatObnovleniyaUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private CanParseYaml $canParseYaml,
        private RunnerService $runnerService
    )
    {
    }

    function handle(UstanovitResultatObnovleniyaUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $resultatObnovleniya = $this->runnerService->poluchitResultatObnovleniyaUnita($unit);

        if ($resultatObnovleniya->success) {
            $config = $this->canParseYaml->parse($resultatObnovleniya->config ?? '');
            $unit->ustanovitUspehObnovleniya($resultatObnovleniya->message, $config);
        } else {
            $unit->ustanovitOshibkuObnovleniya($resultatObnovleniya->message);
        }

        $this->unitRepository->save($unit);
    }
}
