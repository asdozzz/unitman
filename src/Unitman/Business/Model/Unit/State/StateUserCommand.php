<?php

namespace App\Unitman\Business\Model\Unit\State;

enum StateUserCommand: string
{
    case nachatSborku = 'nachatSborku';

    case ustanovitResultatSborki = 'ustanovitResultatSborki';
    case nachatObnovlenie = 'nachatObnovlenie';

    case ustanovitResultatObnovleniya = 'ustanovitResultatObnovleniya';
    case zapolnitPeremenie = 'zapolnitPeremenie';
    case nachatPodgotovku = 'nachatPodgotovku';
    case ustanovitResultatPodgotovki = 'ustanovitResultatPodgotovki';
    case nachatSbrosPodgotovki = 'nachatSbrosPodgotovki';
    case ustanovitResultatSbrosaPodgotovki = 'ustanovitResultatSbrosaPodgotovki';
    case nachatZapusk = 'nachatZapusk';
    case ustanovitResultatZapuska = 'ustanovitResultatZapuska';
    case nachatOstanovku = 'nachatOstanovku';
    case ustanovitResultatOstanovki = 'ustanovitResultatOstanovki';
    case nachatUdalenie = 'nachatUdalenie';
    case ustanovitResultatUdaleniya = 'ustanovitResultatUdaleniya';
    case udalitVruchnuyu = 'udalitVruchnuyu';
    case nachatIzmenenieVetki = 'nachatIzmenenieVetki';
    case ustanovitResultatIzmeneniyaVetki = 'ustanovitResultatIzmeneniyaVetki';
    case nachatObnovleniePosleZapuska = 'nachatObnovleniePosleZapuska';
    case nachatUdaleniePosleZapuska = 'nachatUdaleniePosleZapuska';

    case vipolnitDeistvie = 'vipolnitDeistvie';
    case ustanovitResultatDeistviya = 'ustanovitResultatDeistviya';

    case proveritKonteinerUnita = 'proveritKonteinerUnita';
}
