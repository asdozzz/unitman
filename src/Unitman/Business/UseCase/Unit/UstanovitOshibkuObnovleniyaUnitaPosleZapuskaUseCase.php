<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\UstanovitResultatObnovleniyaUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
    )
    {
    }

    function handle(UstanovitOshibkuObnovleniyaUnitaPosleZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->ustanovitOshibkuObnovleniyaPosleZapuska($command->error);
        $this->unitRepository->save($unit);
    }
}
