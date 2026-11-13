<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\ProzesUnita\ZadachaProzesaUnita;
use App\Unitman\Business\ReadModel\Unit\ProzesUnitaBezShagov;

interface UmeetPoluchatProzesiUnitaPoId
{
    /**
     * @return ProzesUnitaBezShagov[]
     * */
    function poluchitProzesiPoIdUnita(string $unitId): array;

    function poluchitShagiZadachiPoId(string $prozesId, string $zadachaId): array;

    /**
     * Задача процесса по её идентификатору.
     *
     * Используется для проверки принадлежности задачи указанному процессу
     * перед выдачей прогресса выполнения.
     */
    function poluchitZadachuPoId(string $prozesId, string $zadachaId): ZadachaProzesaUnita;
}
