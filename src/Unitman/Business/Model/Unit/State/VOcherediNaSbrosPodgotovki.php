<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class VOcherediNaSbrosPodgotovki extends AbstractState
{

    const CODE = 'JDET_RESULTATI_SBROSA_PODGOTOVKI';

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
            new OshibkaSbrosaPodgotovki(),
            new Sobran(),
            new VOcherediNaUdalenie()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::ustanovitResultatSbrosaPodgotovki
        ];
    }
}
