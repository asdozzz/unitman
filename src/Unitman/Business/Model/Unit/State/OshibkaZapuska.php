<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class OshibkaZapuska extends AbstractState
{

    public function getCode(): string
    {
        return 'OSHIBKA_ZAPUSKA';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new VOcherediNaUdalenie(),
            new VOcherediNaZapusk(),
            new VOcherediNaObnovlenie(),
            new VOcherediNaSbrosPodgotovki()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatZapusk,
            StateUserCommand::nachatObnovlenie,
            StateUserCommand::nachatSbrosPodgotovki
        ];
    }
}
