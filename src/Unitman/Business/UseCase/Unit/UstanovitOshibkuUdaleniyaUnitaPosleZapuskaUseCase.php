<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuUdaleniyaUnitaPosleZapuska;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitOshibkuUdaleniyaUnitaPosleZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
    )
    {
    }

    function handle(UstanovitOshibkuUdaleniyaUnitaPosleZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->ustanovitOshibkaUdaleniyaUnitaPosleZapuska($command->error);
        $this->unitRepository->save($unit);
    }
}
