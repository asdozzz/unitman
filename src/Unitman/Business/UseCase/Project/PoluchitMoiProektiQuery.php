<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Port\Project\CanGetActiveProjectList;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectUsersList;

final class PoluchitMoiProektiQuery
{
    public function __construct(
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokMoihProektov,
        private CanGetActiveProjectList $canGetProjectList,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(PoluchitMoiProekti $command): array
    {
        $userId = $this->securityService->getCurrentUserId();
        $moiProekti = $this->umeetPoluchatSpisokMoihProektov->poluchitSpisokProektovDlyPolzovatelya($userId);
        $projectIds = array_map(fn(ProjectList $project) => $project->id, $moiProekti);
        return $this->canGetProjectList->getActiveListByIds($projectIds);
    }
}
