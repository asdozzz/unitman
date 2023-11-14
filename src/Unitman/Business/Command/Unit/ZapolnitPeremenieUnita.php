<?php

namespace App\Unitman\Business\Command\Unit;

final class ZapolnitPeremenieUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $values
    )
    {
    }

}
