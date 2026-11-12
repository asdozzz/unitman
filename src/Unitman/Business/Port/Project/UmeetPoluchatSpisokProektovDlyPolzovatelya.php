<?php

namespace App\Unitman\Business\Port\Project;


interface UmeetPoluchatSpisokProektovDlyPolzovatelya
{
    function poluchitSpisokProektovDlyPolzovatelya(string $userId): array;
}
