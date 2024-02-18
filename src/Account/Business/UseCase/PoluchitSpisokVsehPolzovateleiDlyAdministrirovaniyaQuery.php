<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetPoluchatSpisokVsehPolzovatelei;
use App\Account\Business\ReadModel\AccountForManaging;

final class PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniyaQuery
{
    public function __construct(
        private SecurityService $securityService,
        private UmeetPoluchatSpisokVsehPolzovatelei $umeetPoluchatSpisokVsehPolzovatelei
    )
    {
    }

    /**
     * @return AccountForManaging[]
     * */
    function handle(): array
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }
        return $this->umeetPoluchatSpisokVsehPolzovatelei->poluchitSpisokVsehPolzovateleiDlyAdministrirovaniya();
    }
}
