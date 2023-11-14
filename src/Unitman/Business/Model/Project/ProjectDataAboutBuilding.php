<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectDataAboutBuilding
{
    public function __construct(
        public readonly string $jobId,
        public readonly bool $isFinish = false,
        public readonly bool $success = false,
        public readonly string $info = ''
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

}
