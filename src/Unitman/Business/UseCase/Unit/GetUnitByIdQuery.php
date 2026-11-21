<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitReadModelById;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\Redaktor\OpredelitelRezhimaRedaktora;

final class GetUnitByIdQuery
{
    public function __construct(
        private CanGetUnitReadModelById $canGetUnitReadModelById,
        private UnitmanSecurityService $securityService,
        private OpredelitelRezhimaRedaktora $opredelitelRezhima,
    )
    {

    }

    function handle(GetUnitReadModelById $command): SpisokUnitovReadModel
    {
        $unit = $this->canGetUnitReadModelById->getById($command->id);

        return $this->opredelitelRezhima->obogatitUnicu(
            $unit,
            $this->securityService->getCurrentUserId(),
        );
    }
}
