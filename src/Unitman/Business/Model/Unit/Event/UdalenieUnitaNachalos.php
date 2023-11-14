<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UdalenieUnitaNachalos
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $jobId,
        public readonly array $stateAsArray
    )
    {
    }

}
