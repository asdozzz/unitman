<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Sobran extends AbstractState
{

    const CODE = 'USPESHNO_SOBRAN';

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
            new VOcherediNaUdalenie(),
            new VOcherediNaObnovlenie(),
            new Sobran(),
            new VOcherediNaPodgotovku(),
            new VOcheredNaIzmenenieVetki()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        $arr = [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovlenie,
            StateUserCommand::zapolnitPeremenie,
            StateUserCommand::nachatIzmenenieVetki,
        ];

        if ($unit->esliConfigZapolnenPravilon()){
            $arr[] = StateUserCommand::nachatPodgotovku;
        }

        return $arr;

    }
}
