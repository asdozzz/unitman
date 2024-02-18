<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\ReadModel\ProjectUsersList;

interface UmeetPoluchatSpisokPolzovateleiProekta
{
    /**
     * @return ProjectUsersList[]
     * */
    function poluchitSpisokPolzovateleiProekta(string $projectId): array;
}
