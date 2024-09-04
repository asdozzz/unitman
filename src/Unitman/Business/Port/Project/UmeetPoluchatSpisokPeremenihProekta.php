<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\ReadModel\ProjectList\ProjectListVariable;

interface UmeetPoluchatSpisokPeremenihProekta
{
    /**
     * @return ProjectListVariable[]
     * */
    function poluchitSpisokPeremenihProekta(string $projectId): array;
}
