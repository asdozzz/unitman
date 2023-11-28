<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaZapusk extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTATI_ZAPUSKA';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new OshibkaZapuska(),
            new Zapushen(),
            new UdalenVruchnuyu()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [];
    }
}
