<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class KodVetkiIzmenilsyaVHranilishe
{
    public function __construct(
        public readonly string $unitId,
        public readonly int $unixtime
    )
    {
    }
}
