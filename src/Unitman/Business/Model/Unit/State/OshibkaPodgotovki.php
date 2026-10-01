<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class OshibkaPodgotovki extends AbstractState
{

    const CODE = 'OSHIBKA_PODGOTOVKI';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getNextStates(): array
    {
        return [
            new VOcherediNaUdalenie(),
            new VOcherediNaObnovlenie(),
            new VOcherediNaPodgotovku()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        $arr = [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovlenie,
            StateUserCommand::zapolnitPeremenie
        ];

        if ($unit->esliConfigZapolnenPravilon()){
            $arr[] =StateUserCommand::nachatPodgotovku;
        }

        return $arr;
    }
}
