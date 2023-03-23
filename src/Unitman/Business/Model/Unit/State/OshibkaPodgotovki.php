<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class OshibkaPodgotovki extends AbstractState
{

    public function getCode(): string
    {
        return 'OSHIBKA_PODGOTOVKI';
    }

    public function getNextStates(): array
    {
        return [
            new VOcherediNaUdalenie(),
            new VOcherediNaObnovlenie(),
            new VOcherediNaPodgotovku()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovlenie,
            StateUserCommand::nachatPodgotovku,
            StateUserCommand::zapolnitPeremenie
        ];
    }
}
