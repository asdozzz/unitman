<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class ZadachaUnitaOtmenena
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $jobId,
        public readonly int $unixtime,
        public readonly array $prozess,
    )
    {
    }
}
