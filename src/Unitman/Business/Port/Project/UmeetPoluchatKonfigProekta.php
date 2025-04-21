<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Repo;

interface UmeetPoluchatKonfigProekta
{
    function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string;
}
