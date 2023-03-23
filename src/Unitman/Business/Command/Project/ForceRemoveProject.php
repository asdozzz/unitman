<?php

namespace App\Unitman\Business\Command\Project;

final class ForceRemoveProject
{
    public function __construct(
        public readonly string $projectId
    )
    {
    }
}
