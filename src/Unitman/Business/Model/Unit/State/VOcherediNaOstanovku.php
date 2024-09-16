<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaOstanovku extends AbstractState
{

    const CODE = 'JDET_RESULTATI_OSTANOVKI';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getNextStates(): array
    {
        return [
            new OshibkaOstanovki(),
            new Podgotovlen(),
            new VOcherediNaUdalenie()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::ustanovitResultatOstanovki
        ];
    }
}
