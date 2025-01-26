<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Zapushen extends AbstractState
{

    public function getCode(): string
    {
        return 'USPESHNO_ZAPUSHEN';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new VOcherediNaOstanovku(),
            new VOcherediNaVipolnenieDeistviya()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatOstanovku,
            StateUserCommand::nachatObnovleniePosleZapuska,
            StateUserCommand::nachatUdaleniePosleZapuska,
            StateUserCommand::vipolnitDeistvie,
            StateUserCommand::proveritKonteinerUnita
        ];
    }
}
