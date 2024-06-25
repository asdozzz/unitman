<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaPodgotovku extends AbstractState
{

    public function getCode(): string
    {
       return 'JDET_RESULTATI_PODGOTOVKI';
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
