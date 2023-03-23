<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitUspehOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehUdaleniya;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitUspehUdaleniyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository
    )
    {
    }

    function handle(UstanovitUspehUdaleniya $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitUspehUdaleniya($command->textFromRunner);
        $this->unitRepository->save($unit);
    }
}
