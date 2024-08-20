<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $error,
    )
    {
    }

}
