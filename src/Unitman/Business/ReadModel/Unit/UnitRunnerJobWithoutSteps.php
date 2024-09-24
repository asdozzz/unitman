<?php

namespace App\Unitman\Business\ReadModel\Unit;

class UnitRunnerJobWithoutSteps
{
    /**
     * @param string $id
     * @param string $unitId
     * @param string $jobType
     * @param bool $success
     */
    public function __construct(
        public string $id,
        public readonly string $unitId,
        public readonly string $jobType,
        public readonly bool $success,
    )
    {
    }

}
