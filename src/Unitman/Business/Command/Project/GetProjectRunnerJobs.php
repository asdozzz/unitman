<?php

namespace App\Unitman\Business\Command\Project;

final class GetProjectRunnerJobs
{
    public function __construct(
        public readonly string $id
    )
    {
    }
}
