<?php

namespace App\Unitman\Business\Port\Repo;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\ReadModel\ProektHranilisha;

interface UmeetPoluchatSpisokProektovHranilisha
{
    /**
     * @return ProektHranilisha[]
     * */
    function poluchitSpisokProektovHranilisha(Repo $repo, ?string $query);
}
