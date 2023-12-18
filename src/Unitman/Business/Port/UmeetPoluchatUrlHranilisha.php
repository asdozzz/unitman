<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Repo\RepoType;

interface UmeetPoluchatUrlHranilisha
{
    function poluchitUrlHranilisha(RepoType $repoType, ?string $repoUrl);
}
