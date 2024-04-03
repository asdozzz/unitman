<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectDataAboutBuilding
{
    public function __construct(
        public readonly string $jobId,
        public readonly bool $isFinish = false,
        public readonly bool $success = false,
        public readonly array $steps = []
    )
    {
    }

    function success(array $steps): static
    {
        return new static($this->jobId, true, true, $steps);
    }

    function fail(array $steps): static
    {
        return new static($this->jobId, true, false, $steps);
    }

}
