<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaUdalenie extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTAT_UDALENIYA';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [new Sloman(), new Udalen(), new UdalenVruchnuyu()];
    }

    public function getCommands(Unit $unit): array
    {
        return [];
    }
}
