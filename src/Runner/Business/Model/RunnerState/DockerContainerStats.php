<?php

namespace App\Runner\Business\Model\RunnerState;

final class DockerContainerStats
{
    public function __construct(
        public string $containerId,
        public string $containerName,
        public string $cpuPercent,
        public string $memoryPercent,
        public string $memoryUsage,
        public string $netIO,
    )
    {
    }

}
