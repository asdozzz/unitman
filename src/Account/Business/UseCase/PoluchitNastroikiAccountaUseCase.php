<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetPoluchatNastroikiAccounta;
use App\Account\Business\ReadModel\AccountSettingsReadModel;

final class PoluchitNastroikiAccountaUseCase
{
    public function __construct(
        private UmeetPoluchatNastroikiAccounta $umeetPoluchatNastroikiAccounta,
        private SecurityService $securityService
    )
    {
    }

    function handle(): AccountSettingsReadModel
    {
        return $this->umeetPoluchatNastroikiAccounta->poluchitNastroikiAccounta($this->securityService->getCurrentUserId());
    }
}
