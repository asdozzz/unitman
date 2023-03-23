<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class SborkaUnitNachalas
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $jobId
    )
    {
    }

}
