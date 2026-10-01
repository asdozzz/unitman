<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Zapushen extends AbstractState
{

    const CODE = 'USPESHNO_ZAPUSHEN';

    public function getCode(): string
    {
        return self::CODE;
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
