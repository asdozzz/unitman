<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\Unit\CanGetUnitList;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class GetUnitListQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private CanGetUnitList $repo
    )
    {
    }

    function handle(GetUnitList $query): array
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        return $this->repo->getList($query);
    }
}
