<?php

namespace App\Unitman\Business\Model\Unit\State;

enum StateUserCommand: string
{
    case nachatSborku = 'nachatSborku';

    case nachatObnovlenie = 'nachatObnovlenie';

    case zapolnitPeremenie = 'zapolnitPeremenie';
    case nachatPodgotovku = 'nachatPodgotovku';
    case ustanovitResultatPodgotovki = 'ustanovitResultatPodgotovki';
    case nachatSbrosPodgotovki = 'nachatSbrosPodgotovki';
    case nachatZapusk = 'nachatZapusk';
    case nachatOstanovku = 'nachatOstanovku';
    case nachatUdalenie = 'nachatUdalenie';
    case udalitVruchnuyu = 'udalitVruchnuyu';
    case nachatObnovleniePosleZapuska = 'nachatObnovleniePosleZapuska';
    case nachatUdaleniePosleZapuska = 'nachatUdaleniePosleZapuska';

    case vipolnitDeistvie = 'vipolnitDeistvie';
    case ustanovitResultatDeistviya = 'ustanovitResultatDeistviya';

    case proveritKonteinerUnita = 'proveritKonteinerUnita';
}
