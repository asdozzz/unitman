<?php

namespace App\Runner\Acl;

use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Unitman\Api\UnitmanApi;
use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;

final class UnitmanAdapter
{
    public function __construct(private UnitmanApi $unitmanApi)
    {
    }

    /**
     * @param DockerContainerStats[] $containerStats
     * */
    function obnovitStatistikuPoUnitam(array $containerStats): void
    {
        if (empty($containerStats)) {
            return;
        }
        foreach ($containerStats as $containerStat) {
            $command = new ObnovitStatistikuPoKontaineruUnita(
                containerName: $containerStat->containerName,
                cpuPercent: $containerStat->cpuPercent,
                memoryPercent: $containerStat->memoryPercent,
                memoryUsage: $containerStat->memoryUsage,
                netIO: $containerStat->netIO,
            );
            $this->unitmanApi->obnovitStatistikuUnita($command);
        }
    }
}
