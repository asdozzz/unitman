<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Podgotovlen extends AbstractState
{

    public function getCode(): string
    {
        return 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            //new VOcherediNaUdalenie(),
            new VOcherediNaObnovlenie(),
            new VOcherediNaSbrosPodgotovki(),
            new VOcherediNaZapusk(),
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            //StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovleniePosleZapuska,
            StateUserCommand::zapolnitPeremenie,
            StateUserCommand::nachatSbrosPodgotovki,
            StateUserCommand::nachatZapusk,
            StateUserCommand::proveritKonteinerUnita
        ];
    }
}
