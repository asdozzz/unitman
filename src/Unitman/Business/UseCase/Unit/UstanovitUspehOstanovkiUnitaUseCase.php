<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitUspehOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehSborkiUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitUspehOstanovkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository
    )
    {
    }

    function handle(UstanovitUspehOstanovkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $unit->ustanovitUspehOstanovki($command->textFromRunner);
        $this->unitRepository->save($unit);
    }
}
