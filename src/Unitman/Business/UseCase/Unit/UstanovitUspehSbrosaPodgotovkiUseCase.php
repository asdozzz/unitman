<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitUspehOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehSbrosaPodgotovki;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitUspehSbrosaPodgotovkiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository
    )
    {
    }

    function handle(UstanovitUspehSbrosaPodgotovki $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitUspehSbrosaPodgotovki($command->textFromRunner);
        $this->unitRepository->save($unit);
    }
}
