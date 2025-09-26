<?php

namespace App\Unitman\Business\ReadModel\Unit\ProzesUnita;

class ZadachaProzesaUnitaBezShagovZadachi
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $userId,
        public readonly string $type,
        public readonly string $state,
    )
    {
    }

}
