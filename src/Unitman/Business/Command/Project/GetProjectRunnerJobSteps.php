<?php

namespace App\Unitman\Business\Command\Project;

final class GetProjectRunnerJobSteps
{
    public function __construct(
        public readonly string $id
    )
    {
    }
}
