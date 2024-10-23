<?php

namespace App\Runner\Business\Model\GolangRunner\Runner;

final class DockerStatsResult
{
    public function __construct(public readonly string $Stats)
    {
    }
}
