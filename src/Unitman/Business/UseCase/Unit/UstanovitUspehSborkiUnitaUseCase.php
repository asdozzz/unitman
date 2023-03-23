<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitUspehSborkiUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\UnitRepository;

final class UstanovitUspehSborkiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private CanParseYaml $canParseYaml
    )
    {
    }

    function handle(UstanovitUspehSborkiUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $config = $this->canParseYaml->parse($command->configText);
        $unit->ustanovitUspehSborki($command->textFromRunner, $config);
        $this->unitRepository->save($unit);
    }
}
