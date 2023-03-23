<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitUspehOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehPodgotovkiUnita;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitUspehPodgotovkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository
    )
    {
    }

    function handle(UstanovitUspehPodgotovkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitUspehPodgotovki($command->textFromRunner);
        $this->unitRepository->save($unit);
    }
}
