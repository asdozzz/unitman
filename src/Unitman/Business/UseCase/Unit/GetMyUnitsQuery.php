<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Port\Unit\CanGetMyUnits;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Unit\Redaktor\OpredelitelRezhimaRedaktora;

final class GetMyUnitsQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private CanGetMyUnits $canGetMyUnits,
        private OpredelitelRezhimaRedaktora $opredelitelRezhima,
    )
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     * */
    function handle(GetMyUnits $command): array
    {
        $currentUserId = $this->securityService->getCurrentUserId();
        $units = $this->canGetMyUnits->getMyUnits($command, $currentUserId);

        return $this->opredelitelRezhima->obogatitSpisok($units, $currentUserId);
    }
}
