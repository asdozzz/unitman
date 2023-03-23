<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuSbrosaPodgotovkiUnita;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitOshibkuSbrosaPodgotovkiUnitaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(UstanovitOshibkuSbrosaPodgotovkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitOshibkuSbrosaPodgotovki($command);
        $this->unitRepository->save($unit);
    }
}
