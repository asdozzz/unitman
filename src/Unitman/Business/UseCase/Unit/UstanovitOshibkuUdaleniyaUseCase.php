<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuUdaleniya;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitOshibkuUdaleniyaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(UstanovitOshibkuUdaleniya $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitOshibkuUdaleniya($command);
        $this->unitRepository->save($unit);
    }
}
