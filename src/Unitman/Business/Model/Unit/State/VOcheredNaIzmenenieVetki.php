<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcheredNaIzmenenieVetki extends AbstractState
{
    const CODE = 'JDET_RESULTATI_IZMENENIYA_VETKI';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getNextStates(): array
    {
        return [
            new Sobran(),
            new VOcherediNaUdalenie(),
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::ustanovitResultatIzmeneniyaVetki
        ];
    }
}
