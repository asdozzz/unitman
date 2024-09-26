<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuAvtosborkiUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitOshibkuAvtosborkiUnitaSystemoiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
    )
    {
    }

    function handle(UstanovitOshibkuAvtosborkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->ustanovitOshibkuAvtosborki($command->error);
        $this->unitRepository->save($unit);
    }
}
