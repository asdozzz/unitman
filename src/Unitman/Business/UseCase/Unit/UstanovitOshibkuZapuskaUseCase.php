<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuZapuska;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitOshibkuZapuskaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(UstanovitOshibkuZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitOshibkuZapuska($command);
        $this->unitRepository->save($unit);
    }
}
