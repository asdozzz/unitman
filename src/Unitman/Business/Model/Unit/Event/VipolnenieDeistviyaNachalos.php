<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class VipolnenieDeistviyaNachalos
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $jobId,
        public readonly string $actionId,
        public readonly array $values,
        public readonly array $stateAsArray,
        public readonly int $unixtime,
    )
    {
    }
}
