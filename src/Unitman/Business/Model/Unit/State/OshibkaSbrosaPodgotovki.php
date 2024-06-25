<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class OshibkaSbrosaPodgotovki extends AbstractState
{

    public function getCode(): string
    {
        return 'OSHIBKA_SBROSA_PODGOTOVKI';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new VOcherediNaUdalenie(),
            new VOcherediNaSbrosPodgotovki(),
            new VOcherediNaObnovlenie(),
        ];
    }

    public function getCommands(Unit $unit): array
    {
        $arr = [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovlenie,
            StateUserCommand::zapolnitPeremenie,
            StateUserCommand::nachatSbrosPodgotovki
        ];

        return $arr;
    }
}
