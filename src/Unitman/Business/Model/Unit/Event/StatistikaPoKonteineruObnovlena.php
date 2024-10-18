<?php

namespace App\Unitman\Business\Model\Unit\Event;

readonly class StatistikaPoKonteineruObnovlena
{
    public function __construct(
        public string $id,
        public string $cpuPercent,
        public string $memoryPercent,
        public string $memoryUsage,
        public string $netIO
    )
    {

    }
}
