<?php

namespace App\Unitman\Business\Model\Unit\Runner;

final class RunnerJobStep
{
    public function __construct(public readonly string $command, public readonly string $response, public readonly bool $success, public readonly int $unixtime)
    {
    }

}
