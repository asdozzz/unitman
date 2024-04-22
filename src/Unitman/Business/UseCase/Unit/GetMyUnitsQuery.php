<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Port\Unit\CanGetMyUnits;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

final class GetMyUnitsQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private CanGetMyUnits $canGetMyUnits
    )
    {
    }

    /**
     * @return SpisokUnitovReadModel[]
     * */
    function handle(GetMyUnits $command): array
    {
        return $this->canGetMyUnits->getMyUnits($command, $this->securityService->getCurrentUserId());
    }
}
