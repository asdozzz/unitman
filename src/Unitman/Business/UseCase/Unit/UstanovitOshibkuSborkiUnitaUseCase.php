<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitOshibkuSborkiUnitaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(UstanovitOshibkuSborkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitOshibkuSborki($command);
        $this->unitRepository->save($unit);
    }
}
