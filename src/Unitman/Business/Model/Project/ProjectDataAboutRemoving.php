<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectDataAboutRemoving
{
    public function __construct(
        public readonly string $jobId,
        public readonly bool $isFinish = false,
        public readonly bool $success = false,
        public readonly string $info = '',
        public readonly bool $manually = false,
    )
    {
    }

    function success(string $info): static
    {
        return new static($this->jobId, true, true, $info);
    }

    function fail(string $error): static
    {
        return new static($this->jobId, true, false, $error);
    }

    function removeManually(): static
    {
        return new static($this->jobId, $this->isFinish, $this->success, $this->info, true);
    }
}
