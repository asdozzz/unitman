<?php

namespace App\Runner\Business\Model\GolangRunner\Runner;

final class RunnerHealthCheckResult
{
    public function __construct(public readonly bool $Success)
    {
    }
}
