<?php

namespace App\Unitman\Business\Model\Unit\UnitProcess;

enum UnitProcessType: string
{
    case SBORKA = 'SBORKA';
    case OBNOVLENIE = 'OBNOVLENIE';

    case PODGOTOVKA = 'PODGOTOVKA';
    case ZAPUSK = 'ZAPUSK';
    case OSTANOVKA = 'OSTANOVKA';

    case SBROS_PODGOTOVKI = 'SBROS_PODGOTOVKI';

    case UDALENIE = 'UDALENIE';

    case DEISTVIE = 'DEISTVIE';
}
