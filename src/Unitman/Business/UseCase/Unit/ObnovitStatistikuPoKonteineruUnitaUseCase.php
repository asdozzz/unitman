<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Port\Unit\CanFindUnitIdByContainerName;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatStatistikuKonteinera;
use App\Unitman\Business\ReadModel\Unit\ProjectListContainerStats;

final class ObnovitStatistikuPoKonteineruUnitaUseCase
{
    public function __construct(
        private UmeetObnovlyatStatistikuKonteinera $umeetObnovlyatStatistikuKonteinera,
        private CanFindUnitIdByContainerName $canFindUnitIdByContainerName
    )
    {
    }

    function handle(ObnovitStatistikuPoKontaineruUnita $command): void
    {
        $id = $this->canFindUnitIdByContainerName->findIdByNameAndProjectName($command->containerName);

        if (empty($id)) {
            return;
        }

        $this->umeetObnovlyatStatistikuKonteinera->obnovitStatistikuContainer($id, new ProjectListContainerStats(
            $command->cpuPercent,
            $command->memoryPercent,
            $command->memoryUsage,
            $command->netIO
        ));
    }
}
