<?php

namespace App\Unitman\Business\Model\Unit;

readonly class StatistikaKonteinera
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
