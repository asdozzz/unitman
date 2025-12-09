<?php

namespace App\Unitman\Business\ReadModel\Unit\ProzesUnita;

class ZadachaProzesaUnitaBezShagovZadachi
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $state,
    )
    {
    }

}
