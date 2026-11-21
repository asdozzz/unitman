<?php

namespace App\Unitman\Business\Model\SobitieIzHranilisha;

enum TipSobitiya: string
{
    case KOD_OBNOVLEN = 'KOD_OBNOVLEN';
    case VETKA_UDALENA = 'VETKA_UDALENA';

    case VETKA_SOZDANA = 'VETKA_SOZDANA';

    /**
     * Ветка юнита слита в целевую ветку проекта. В отличие от
     * {@see self::VETKA_UDALENA}, ветка может продолжать существовать, но работа
     * над юнитом завершена, поэтому событие приводит к тому же намерению
     * удаления.
     */
    case VETKA_SMERZHENA = 'VETKA_SMERZHENA';
}
