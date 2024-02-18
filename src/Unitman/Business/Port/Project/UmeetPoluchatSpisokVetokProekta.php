<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\ReadModel\VetkaProekta;

interface UmeetPoluchatSpisokVetokProekta
{
    /**
     * @return VetkaProekta[]
     * */
    function poluchitVetkiProekta(Repo $repo, string $projectCode): array;
}
