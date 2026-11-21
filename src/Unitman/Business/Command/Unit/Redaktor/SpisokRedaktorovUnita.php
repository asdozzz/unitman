<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\Redaktor;

final class SpisokRedaktorovUnita
{
    public function __construct(
        public readonly string $unitId,
    )
    {
    }
}
