<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Sobran extends AbstractState
{

    public function getCode(): string
    {
        return 'USPESHNO_SOBRAN';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new VOcherediNaUdalenie(),
            new VOcherediNaObnovlenie(),
            new Sobran(),
            new VOcherediNaPodgotovku()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        $arr = [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovlenie,
            StateUserCommand::zapolnitPeremenie,

        ];

        if ($unit->esliConfigZapolnenPravilon()){
            $arr[] = StateUserCommand::nachatPodgotovku;
        }

        return $arr;

    }
}
