<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\ReadModel\Unit\ProzesUnita\ZadachaProzesaUnita;

final class ProzesUnita
{
    /**
     * @param ZadachaProzesaUnita[] $jobs
     * */
    public function __construct(
        public readonly string $id,
        public readonly string $unitId,
        public readonly string $userId,
        public readonly string $type,
        public readonly string $state,
        public readonly array $jobs
    )
    {
    }

}
