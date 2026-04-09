<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\ReadModel\ProjectList;

interface UmeetPoluchatSpisokProektovDlyPolzovatelya
{
    function poluchitSpisokProektovDlyPolzovatelya(string $userId): array;
}
