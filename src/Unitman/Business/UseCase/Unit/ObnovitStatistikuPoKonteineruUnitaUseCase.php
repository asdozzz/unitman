<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Port\Unit\CanFindUnitIdByContainerName;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\ProjectListContainerStats;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;

final class ObnovitStatistikuPoKonteineruUnitaUseCase
{
    public function __construct(
        private SpisokUnitovRepository $spisokUnitovRepository,
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

        $readModel = $this->spisokUnitovRepository->findById($id);

        if (empty($readModel)) {
            return;
        }

        $readModel = $readModel->copyAndUpdateData([
            'statistikaKonteinera' => new ProjectListContainerStats(
                $command->cpuPercent,
                $command->memoryPercent,
                $command->memoryUsage,
                $command->netIO
            ),
        ]);

        $this->spisokUnitovRepository->update($readModel);
    }
}
