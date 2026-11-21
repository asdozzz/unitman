<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\Redaktor;

final class PoluchitSsylkuRedaktora
{
    public function __construct(
        public readonly string $unitId,
    )
    {
    }
}
