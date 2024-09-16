<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaUdalenie extends AbstractState
{

    const CODE = 'JDET_RESULTAT_UDALENIYA';

    public function getCode(): string
    {
        return self::CODE;
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
        return [
            StateUserCommand::ustanovitResultatUdaleniya
        ];
    }
}
