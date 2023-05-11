<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Udalen extends AbstractState
{

    public function getCode(): string
    {
        return 'UDALEN';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [];
    }

    public function getCommands(Unit $unit): array
    {
        return [];
    }
}
