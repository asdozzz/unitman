<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitOshibkuObnovleniyaUnitaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(UstanovitOshibkuObnovleniyaUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitOshibkuObnovleniya($command);
        $this->unitRepository->save($unit);
    }
}
