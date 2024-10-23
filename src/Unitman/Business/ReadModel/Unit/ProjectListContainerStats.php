<?php

namespace App\Unitman\Business\ReadModel\Unit;

readonly class ProjectListContainerStats
{
    public function __construct(
        public string $cpuPercent,
        public string $memoryPercent,
        public string $memoryUsage,
        public string $netIO,
    )
    {
    }
}
