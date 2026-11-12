<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PoluchitMoiProektiQuery
{
    public function __construct(
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokMoihProektov,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(PoluchitMoiProekti $command): array
    {
        $userId = $this->securityService->getCurrentUserId();
        return $this->umeetPoluchatSpisokMoihProektov->poluchitSpisokProektovDlyPolzovatelya($userId);
    }
}
