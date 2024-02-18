<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\ReadModel\ProjectUsersList;

interface UmeetPoluchatSpisokProektovDlyPolzovatelya
{
    /**
     * @return ProjectUsersList[]
     * */
    function poluchitSpisokProektovDlyPolzovatelya(string $userId): array;
}
