<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectDataAboutRemoving
{
    public function __construct(
        public readonly string $jobId,
        public readonly bool $isFinish = false,
        public readonly bool $success = false,
        public readonly array $steps = [],
        public readonly bool $manually = false,
    )
    {
    }

    function success(array $info): static
    {
        return new static($this->jobId, true, true, $info);
    }

    function fail(array $error): static
    {
        return new static($this->jobId, true, false, $error);
    }

    function removeManually(): static
    {
        return new static($this->jobId, $this->isFinish, $this->success, $this->steps, true);
    }
}
