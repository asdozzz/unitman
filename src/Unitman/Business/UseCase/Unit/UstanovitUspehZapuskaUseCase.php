<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitUspehOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehZapuska;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitUspehZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository
    )
    {
    }

    function handle(UstanovitUspehZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitUspehZapuska($command->textFromRunner);
        $this->unitRepository->save($unit);
    }
}
