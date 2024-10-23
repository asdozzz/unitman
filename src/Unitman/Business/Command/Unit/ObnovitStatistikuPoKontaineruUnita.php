<?php

namespace App\Unitman\Business\Command\Unit;

readonly class ObnovitStatistikuPoKontaineruUnita
{
    public function __construct(
        public string $containerName,
        public string $cpuPercent,
        public string $memoryPercent,
        public string $memoryUsage,
        public string $netIO,
    )
    {
    }

}
