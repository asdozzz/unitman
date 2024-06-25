<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaObnovlenie extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTATI_OBNOVLENIYA';
    }

    public function getNextStates(): array
    {
        return [
            new OshibkaObnovleniya(),
            new Sobran(),
            new Podgotovlen(),
            new VOcherediNaUdalenie()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::ustanovitResultatObnovleniya
        ];
    }
}
