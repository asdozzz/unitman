<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class IzmenenieVetkiNachalos
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $jobId,
        public readonly string $newBranch,
        public readonly array $stateAsArray
    )
    {
    }
}
