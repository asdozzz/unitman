<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Port\UnitRepository;

final class UdalitSlomaniyUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository
    )
    {
    }

    function handle(UdalitSlomaniyUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->udalitSlomaniyUnit();
        $this->unitRepository->save($unit);
    }
}
