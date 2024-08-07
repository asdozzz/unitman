<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class ObnovlenieKodaUnitaPosleZapuskaNachalos
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $jobId
    )
    {
    }
}
