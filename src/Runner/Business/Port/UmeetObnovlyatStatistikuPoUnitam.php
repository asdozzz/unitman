<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RunnerState\DockerContainerStats;

interface UmeetObnovlyatStatistikuPoUnitam
{
    /**
     * @param DockerContainerStats[] $containerStats
     */
    public function obnovitStatistikuPoUnitam(array $containerStats): void;
}
