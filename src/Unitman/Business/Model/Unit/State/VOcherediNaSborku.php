<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaSborku extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTATI_SBORKI';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new OshibkaSborki(),
            new Sobran()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [];
    }
}
