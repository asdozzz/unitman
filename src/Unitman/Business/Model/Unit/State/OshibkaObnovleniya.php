<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class OshibkaObnovleniya extends AbstractState
{
    public function getCode(): string
    {
        return 'OSHIBKA_OBNOVLENIYA';
    }

    public function getNextStates(): array
    {
        return [
            new VOcherediNaUdalenie(),
            new VOcherediNaObnovlenie(),
            new VOcherediNaPodgotovku(),
            new VOcherediNaZapusk()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        $arr = [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatObnovlenie
        ];
        if ($unit->esliPodgotovlen()) {
            $arr[] = StateUserCommand::nachatZapusk;
        } else {
            $arr[] = StateUserCommand::nachatPodgotovku;
        }
        return $arr;
    }
}
