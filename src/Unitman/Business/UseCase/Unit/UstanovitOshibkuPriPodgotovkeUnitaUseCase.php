<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuPriPodgotovkeUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitOshibkuPriPodgotovkeUnitaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(UstanovitOshibkuPriPodgotovkeUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitOshibkuPodgotovki($command);
        $this->unitRepository->save($unit);
    }
}
