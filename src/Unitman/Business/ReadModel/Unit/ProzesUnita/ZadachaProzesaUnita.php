<?php

namespace App\Unitman\Business\ReadModel\Unit\ProzesUnita;

final class ZadachaProzesaUnita
{
    /**
     * @param ShagZadachiUnita[] $steps
     * */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $state,
        public readonly array $steps = []
    )
    {
    }

}
