<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaPodgotovku extends AbstractState
{

    const CODE = 'JDET_RESULTATI_PODGOTOVKI';

    public function getCode(): string
    {
       return self::CODE;
    }

    public function getNextStates(): array
    {
        return [
            new OshibkaPodgotovki(),
            new Podgotovlen(),
            new VOcherediNaUdalenie()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::ustanovitResultatPodgotovki
        ];
    }
}
