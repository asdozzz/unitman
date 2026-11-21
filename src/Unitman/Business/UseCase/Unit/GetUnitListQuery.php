<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\CanGetUnitList;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Unit\Redaktor\OpredelitelRezhimaRedaktora;

final class GetUnitListQuery
{
    public function __construct(
        private CanGetUnitList $repo,
        private UnitmanSecurityService $securityService,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokProektovDlyPolzovatelya,
        private OpredelitelRezhimaRedaktora $opredelitelRezhima,
    )
    {
    }

    function handle(GetUnitList $query): array
    {
        $currentUserId = $this->securityService->getCurrentUserId();
        $moiProekti = $this->umeetPoluchatSpisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($currentUserId);
        $projectIds = array_map(fn(array $project): string => $project['id'], $moiProekti);
        $units = $this->repo->getList($query, $currentUserId, $projectIds);

        return $this->opredelitelRezhima->obogatitSpisok($units, $currentUserId);
    }
}
