<?php

namespace App\Unitman\Business\Model\Unit\State;

enum StateUserCommand: string
{
    case nachatSborku = 'nachatSborku';
    case nachatObnovlenie = 'nachatObnovlenie';
    case zapolnitPeremenie = 'zapolnitPeremenie';
    case nachatPodgotovku = 'nachatPodgotovku';

    case nachatSbrosPodgotovki = 'nachatSbrosPodgotovki';
    case nachatZapusk = 'nachatZapusk';
    case nachatOstanovku = 'nachatOstanovku';
    case nachatUdalenie = 'nachatUdalenie';
    case udalitVruchnuyu = 'udalitVruchnuyu';
}
