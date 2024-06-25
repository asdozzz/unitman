<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaOstanovku extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTATI_OSTANOVKI';
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
