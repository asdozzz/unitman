<?php

namespace App\Unitman\Business\Port\Unit;

interface UmeetOtbiratNeaktivnieUniti
{
    /**
     * @return string[]
     * */
    function otobratNeaktivnieUniti(int $unixtime): array;
}
