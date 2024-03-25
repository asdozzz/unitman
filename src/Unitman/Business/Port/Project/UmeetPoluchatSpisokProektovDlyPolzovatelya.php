<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\ReadModel\ProjectList;

interface UmeetPoluchatSpisokProektovDlyPolzovatelya
{
    /**
     * @return ProjectList[]
     * */
    function poluchitSpisokProektovDlyPolzovatelya(string $userId): array;
}
