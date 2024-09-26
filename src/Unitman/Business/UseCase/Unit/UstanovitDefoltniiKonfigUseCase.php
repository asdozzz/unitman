<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitDefoltniiKonfigUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handleSystem(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $configVariables = $unit->getConfig()?->getVariables();

        if (empty($configVariables)) {
            throw new \Exception('unit.konfig_not_found');
        }

        $values = [];

        foreach ($configVariables as $variable) {
            $values[$variable->getId()] = $variable->getDefaultValue();
        }

        $unit->zapolnitPeremenie($values);
        $this->unitRepository->save($unit);
    }
}
