<?php

namespace App\Unitman\Business\Model\Unit\State;

final class StateFactory
{

    static function makeByCode(string $code): AbstractState
    {
        $all = [
            new OshibkaSborki(),
            new OshibkaObnovleniya(),
            new OshibkaOstanovki(),
            new OshibkaPodgotovki(),
            new OshibkaSborki(),
            new OshibkaSbrosaPodgotovki(),
            new OshibkaZapuska(),
            new Podgotovlen(),
            new Sloman(),
            new Sobran(),
            new Sozdan(),
            new Udalen(),
            new UdalenVruchnuyu(),
            new VOcherediNaObnovlenie(),
            new VOcherediNaOstanovku(),
            new VOcherediNaPodgotovku(),
            new VOcherediNaSborku(),
            new VOcherediNaSbrosPodgotovki(),
            new VOcherediNaUdalenie(),
            new VOcherediNaZapusk(),
            new Zapushen(),
            new VOcheredNaIzmenenieVetki(),
            new VOcherediNaVipolnenieDeistviya(),
        ];

        $result = null;

        foreach ($all as $state) {
            if ($state->getCode() === $code) {
                $result = $state;
            }
        }

        if (empty($result)) {
            throw new \DomainException('unit.state_factory.state_not_found');
        }

        return $result;
    }
}
