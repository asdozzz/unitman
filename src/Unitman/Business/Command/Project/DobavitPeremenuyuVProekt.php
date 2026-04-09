<?php

namespace App\Unitman\Business\Command\Project;

final class DobavitPeremenuyuVProekt
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $tip,
        public readonly string $code,
        public readonly string $value,
    ) { }
}
