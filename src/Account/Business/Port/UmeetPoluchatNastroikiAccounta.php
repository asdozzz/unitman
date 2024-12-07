<?php

namespace App\Account\Business\Port;

use App\Account\Business\ReadModel\AccountSettingsReadModel;

interface UmeetPoluchatNastroikiAccounta
{
    function poluchitNastroikiAccounta(string $accountId): AccountSettingsReadModel;
}
