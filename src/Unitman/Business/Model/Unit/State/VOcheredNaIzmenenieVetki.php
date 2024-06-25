<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcheredNaIzmenenieVetki extends AbstractState
{
    public function getCode(): string
    {
        return 'JDET_RESULTATI_IZMENENIYA_VETKI';
    }

    public function getNextStates(): array
    {
        return [
            new Sobran(),
            new UdalenVruchnuyu()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::ustanovitResultatIzmeneniyaVetki
        ];
    }
}
