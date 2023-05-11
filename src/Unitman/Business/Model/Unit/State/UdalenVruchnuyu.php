<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class UdalenVruchnuyu extends AbstractState
{

    public function getCode(): string
    {
        return 'UDALEN_VRUCHNUYU';
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
