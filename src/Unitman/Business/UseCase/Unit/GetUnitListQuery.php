<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\Unit\CanGetUnitList;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class GetUnitListQuery
{
    public function __construct(
        private CanGetUnitList $repo,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(GetUnitList $query): array
    {
        return $this->repo->getList($query, $this->securityService->getCurrentUserId());
    }
}
