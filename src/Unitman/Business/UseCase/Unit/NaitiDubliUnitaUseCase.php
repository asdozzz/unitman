<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\NaitiDubliUnita;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

final class NaitiDubliUnitaUseCase
{
    public function __construct(private CanFindUnitDouble $canFindUnitDouble)
    {
    }

    /**
     * @return SpisokUnitovReadModel[]
     * */
    function handle(NaitiDubliUnita $command): array
    {
        return $this->canFindUnitDouble->naitiDubliPoVetke($command->projectId, $command->branch);
    }
}
