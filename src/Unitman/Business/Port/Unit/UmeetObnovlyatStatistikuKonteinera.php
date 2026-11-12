<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\ProjectListContainerStats;

interface UmeetObnovlyatStatistikuKonteinera
{
    public function obnovitStatistikuContainer(string $id, ProjectListContainerStats $containerStats): void;
}
