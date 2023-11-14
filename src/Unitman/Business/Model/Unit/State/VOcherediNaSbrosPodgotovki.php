<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaSbrosPodgotovki extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTATI_SBROSA_PODGOTOVKI';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new OshibkaSbrosaPodgotovki(),
            new Sobran()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [];
    }
}
