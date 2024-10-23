<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Port\Unit\CanFindUnitIdByContainerName;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class ObnovitStatistikuPoKonteineruUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
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

        $unit = $this->unitRepository->getById($id);

        if ($unit->isWaitResultFromRunner()) {
            return;
        }

        $unit->onbovitStatistikuKonteinera($command);
        $this->unitRepository->save($unit);
    }
}
