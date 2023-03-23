<?php

namespace App\Unitman\Business\Command\Unit;

final class SozdatUnit
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch
    )
    {
    }
}
