<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class ZadachiDobavleniVProzesUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $process,
    )
    {
    }
}
